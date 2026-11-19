# PHP / WordPress multi-environment tooling landscape

Research date: 2026-09-10. Findings should be re-verified after ~3 months (tools/docs change fast).

**Evidence grading used throughout:**

- **[SOURCE]** — read directly from the tool's own source code at a known commit (strongest).
- **[DOC]** — first-party documentation.
- **[COMMUNITY]** — GitHub issue/discussion, forum post, or third-party blog. Explicitly *not* a documented feature.
- **UNVERIFIED** — could not confirm; the attempt is described.

**Primary-source checkouts used:**

- `trellis-cli` at `/home/yevhenii/www/trellis-cli`, commit `3beef80ad6532d2482d1bef551b778b4cd083ac3`, dated 2026-09-08, `git describe` = `v1.19.0-44-g3beef80`. This is ~2 days before the research date, so it reflects current `master`.
- `lerd` at `/home/yevhenii/www/lerd`.
- `lerd-env/frameworks` framework store, fetched from GitHub `main`.

---

## Q1. Trellis: provisioning, environments, group_vars, Vagrant/Lima

### Bare VPS provisioning

Trellis provisions **bare servers only**, not shared/managed hosting. The docs state it requires "a bare server running a bare/stock version of Ubuntu 24.04 LTS" and that Trellis "cannot provision shared or managed hosts" ([DOC], <https://roots.io/trellis/docs/remote-server-setup/>).

Two provisioning paths exist:

1. **Managed provider creation** — `trellis server create` creates *and* provisions a cloud server. DigitalOcean and Hetzner Cloud are the implemented providers ([SOURCE], `cmd/server_create.go`, `cmd/server_dns.go`; the command registry in `main.go` registers `server`, `server create`, `server dns` with synopsis `"Commands for cloud server management"`). DigitalOcean requires `DIGITALOCEAN_ACCESS_TOKEN`; Hetzner requires `HCLOUD_TOKEN` ([DOC], <https://roots.io/trellis/docs/deploy-to-hetzner-cloud/>).
2. **Arbitrary SSH host** — provisioning an already-existing server is done with the `server.yml` playbook against a host listed in the environment's `hosts/` inventory file, i.e. any machine reachable over SSH running stock Ubuntu 24.04 ([DOC], <https://roots.io/trellis/docs/remote-server-setup/>). `trellis provision <environment>` drives this ([SOURCE], `main.go` registers `provision`; `cmd/provision.go`).

The provider abstraction was made pluggable in Dec 2025, and `trellis server create` **replaced** the older `trellis droplet create` ([DOC], <https://roots.io/whats-new-in-trellis-december-2025/>). The old command is retained but marked deprecated in the source: the `droplet` namespace synopsis reads verbatim `"Commands for DigitalOcean Droplets (deprecated: use 'server' instead)"` ([SOURCE], `main.go`).

> **So: Hetzner and DigitalOcean are first-class with turnkey creation; any other VPS is supported but you create the machine yourself and only let Trellis provision it.**

### Environments modelled out of the box

The repository ships four `group_vars` directories: `all`, `development`, `production`, `staging` ([SOURCE], `gh api repos/roots/trellis/contents/group_vars`). So the out-of-the-box model is **development / staging / production**, plus a shared `all`.

Critically, this set is **not hardcoded**. trellis-cli discovers environments by globbing the filesystem:

```go
configPaths, _ := filepath.Glob("group_vars/*/wordpress_sites.yml")

for i, p := range configPaths {
    parts := strings.Split(p, string(os.PathSeparator))
    envName := parts[1]
    envs[i] = envName
    t.Environments[envName] = t.ParseConfig(p)
}
```

([SOURCE], `trellis/trellis.go`, lines ~209–220.)

**Any directory under `group_vars/` containing a `wordpress_sites.yml` becomes a valid environment name.** Adding a fourth or fifth environment is therefore supported by construction, and the community does exactly this ([COMMUNITY], <https://discourse.roots.io/t/extending-trellis-to-four-environments/14306>).

This is the single most important nuance for Q4: environments are **arbitrary in name but static in nature** — each is a committed directory tree plus a `hosts/` inventory file. There is no mechanism to create or destroy one on demand. See Q4.

Note also that `group_vars/all` is *excluded* from the environment list by this glob, because the CLI keys on the presence of `wordpress_sites.yml`.

### Inheritance from a shared base

`group_vars/all/` is the shared base. Inheritance is **Ansible's native `group_vars` precedence mechanism, not something Trellis implements** — Ansible merges `group_vars/all` into every play and the per-environment directory overrides it. The docs describe the per-site pattern as copying `wordpress_sites` from `group_vars/development/wordpress_sites.yml` into `group_vars/<environment>/wordpress_sites.yml` ([DOC], <https://roots.io/trellis/docs/remote-server-setup/>).

For the nested `env` dictionary specifically, the multisite docs confirm a genuine merge rather than a wholesale replace: you "can define the env dictionary for more multisite specific settings such as `DOMAIN_CURRENT_SITE` or `PATH_CURRENT_SITE`", and this "gets merged with Trellis' defaults so you don't need to re-define all properties" ([DOC], <https://roots.io/trellis/docs/multisite/>).

At the trellis-cli layer, note that `ParseConfig` reads only the environment's own `wordpress_sites.yml` ([SOURCE], `trellis/trellis.go`). So the CLI's view of a site does not itself resolve `group_vars/all` inheritance — that resolution happens only when Ansible runs.

### Vagrant / Lima

**Vagrant is fully removed, not optional.** Roots published "Trellis Drops Vagrant Support in Favor of Lima VMs" ([DOC/first-party blog], <https://roots.io/trellis-drops-vagrant-support-in-favor-of-lima-vms/>), and the current docs state Trellis "uses Lima VM's for local development" ([DOC], <https://roots.io/trellis/docs/local-development/>). Lima was originally introduced as experimental in trellis-cli v1.10.0 ([DOC], <https://roots.io/introducing-lima-to-trellis-for-faster-local-development/>).

Confirmed at source: the only real VM manager is Lima (plus a `mock` used for tests).

```go
func (t *Trellis) VmManagerType() string {
	switch t.CliConfig.Vm.Manager {
	case "auto":
		if runtime.GOOS == "darwin" || runtime.GOOS == "linux" {
			return "lima"
		}
		return ""
	...
```

([SOURCE], `trellis/trellis.go`, lines 387–401.)

**A local VM is optional in the sense that Trellis can be used purely for remote deploy** — Roots documents using another local environment and using Trellis only for provisioning/deployment ([DOC], <https://roots.io/bedrock/docs/local-development/>). But *if* you want Trellis's own local environment, Lima is the only supported route.

**Linux support has landed since the widely-cited breakage.** GitHub issue #599 ("Did you just drop Linux support?") reported `Error: No VM managers are supported on linux yet.` on v1.15.0 ([COMMUNITY], <https://github.com/roots/trellis-cli/issues/599>). At the current HEAD this is **fixed**: `VmManagerType()` returns `lima` for `runtime.GOOS == "linux"`, and there is dedicated Linux plumbing — `ensureLinuxTapDevice()` and `ensureQEMUWrapper()` ([SOURCE], `pkg/lima/manager.go`, lines ~263, ~274, ~359, ~550).

On Linux the VM type is QEMU rather than Apple's Virtualization.framework:

```go
if runtime.GOOS == "linux" {
    instance.VMType = "qemu"
} else {
    instance.VMType = "vz"
}
```

([SOURCE], `pkg/lima/manager.go`, lines ~358–363.)

The help text states verbatim: `Local VM support requires macOS 13.0+ or Linux with Lima and QEMU/KVM.` ([SOURCE], `cmd/vm_start.go` line 113.)

**This matters enormously for the target platform — see Q9 and Implications.** trellis-cli warns at source when KVM is unavailable: `"Warning: KVM is not available (/dev/kvm not found). QEMU will run without hardware acceleration and will be very slow."` ([SOURCE], `pkg/lima/manager.go` line 491).

---

## Q2. trellis-cli: full command list, DB push/pull, search-replace, uploads sync

### Full current command list

Taken from the command registry at HEAD ([SOURCE], `main.go`, lines 61–215). This is authoritative and supersedes the docs page, which omits some subcommands.

| Command | Synopsis (verbatim from source or docs) |
|---|---|
| `alias` | Generate WP CLI aliases for remote environments |
| `check` | Checks if the required and optional Trellis dependencies are installed |
| `db` | `"Commands for database management"` (namespace) |
| `db open` | Opens database in a GUI client |
| `deploy` | Deploys a site to the specified environment |
| `dotenv` | Template .env files to local system |
| `droplet` | `"Commands for DigitalOcean Droplets (deprecated: use 'server' instead)"` |
| `droplet create` | Create a DigitalOcean droplet (deprecated) |
| `droplet dns` | Manage droplet DNS (deprecated) |
| `server` | `"Commands for cloud server management"` |
| `server create` | Create and provision a cloud server |
| `server dns` | Manage server DNS |
| `exec` | Exec runs a command in the Trellis virtualenv |
| `galaxy` | `"Commands for Ansible Galaxy"` |
| `galaxy install` | Install Galaxy roles |
| `info` | Displays information about this Trellis project |
| `init` | Initializes an existing Trellis project |
| `key` | `"Commands for managing SSH keys"` |
| `key generate` | Generate and add an SSH key |
| `logs` | Tails the Nginx log files for an environment |
| `new` | Creates a new Trellis project |
| `open` | Opens user-defined URLs which can act as shortcuts/bookmarks |
| `provision` | Provisions the specified environment |
| `rollback` | Rollback the last deploy of the site on the specified environment |
| `shell-init` | Prints a script which can be eval'd to set up virtualenv integration |
| `ssh` | Connects to host via SSH |
| `vault` | `"Commands for Ansible Vault"` |
| `vault edit` / `encrypt` / `decrypt` / `view` | Ansible Vault operations |
| `valet` | `"Commands for Laravel Valet"` |
| `valet link` | Link project into Valet |
| `venv hook` | Virtualenv shell hook |
| `vm` | `"Commands for managing development virtual machines"` |
| `vm start` / `stop` / `delete` / `shell` | Lima VM lifecycle |
| `vm sudoers` / `trust` / `trust paths` / `untrust` | VM host trust + sudoers config |
| `xdebug-tunnel` | `"Commands for Xdebug tunnel"` |
| `xdebug-tunnel open` / `close` | Xdebug tunnel lifecycle |

The published docs list a compatible but slightly coarser set ([DOC], <https://roots.io/trellis/docs/cli/>).

### DB push/pull between environments — DOES NOT EXIST

**Decisive, verified at source.** The entire `db` namespace has exactly **one** subcommand:

```go
"db": func() (cli.Command, error) {
    return &cmd.NamespaceCommand{
        HelpText:     "Usage: trellis db <subcommand> [<args>]",
        SynopsisText: "Commands for database management",
    }, nil
},
"db open": func() (cli.Command, error) {
    return cmd.NewDBOpenCommand(ui, trellis), nil
},
```

([SOURCE], `main.go`, lines 68–76.) The `cmd/` directory contains `db_open.go` and `db_open_test.go` and **no other `db_*` file** ([SOURCE], `ls cmd/`).

`trellis db open` merely opens the database in a GUI client (Sequel Ace, TablePlus, etc.). The docs confirm the scope: "Accessing your databases with client software like Sequel Pro, Sequel Ace and TablePlus is straight forward with trellis-cli" ([DOC], <https://roots.io/trellis/docs/database-access/>). It transfers nothing.

A repo-wide grep for the relevant capabilities returns **zero non-test matches**:

```
grep -rniE "search-replace|search_replace|uploads|rsync|\"db push\"|\"db pull\"" --include='*.go' .
# (no output)
```

([SOURCE], run against the checkout at `3beef80`.)

**Conclusion: trellis-cli performs no database transfer, no WordPress search-replace of domains, and no uploads/media sync whatsoever.**

### Uploads / media sync — DOES NOT EXIST

Same grep, same result: zero matches for `uploads` or `rsync`. There is an **open** feature request to add an `rsync` command, proposing e.g. `trellis rsync pull production example {site}/shared/uploads source/uploads`, with a comment suggesting the CLI could also migrate databases and run search-replace ([COMMUNITY], <https://github.com/roots/trellis-cli/issues/109>). It is a request, not a feature.

### Where this lives instead: user-contributed extensions

Roots made a deliberate decision **not** to build this in. On the enhancement issue "Syncing db and uploads between envs (proposed enhancement)" (roots/trellis#654, closed 2020-04-07 as `completed` after 31 comments), maintainer `retlehs` wrote verbatim:

> "the issue with putting something like this directly into trellis is that it's going to have to be opinionated. it's hard to try to account for different types of setups - such as multisite, media libraries that are on CDNs, etc.
>
> i've added trellis-db-push-and-pull from @hamedb89 to the https://roots.io/trellis/docs/user-contributed-extensions/"

([COMMUNITY — maintainer statement on a first-party repo], <https://github.com/roots/trellis/issues/654>; comment dated 2017-11-18.)

Note the reasoning names **multisite specifically** as a reason the feature was rejected as un-generalisable. That is directly relevant to the project in question.

The officially-linked third-party options, from the Roots docs page ([DOC listing of COMMUNITY tools], <https://roots.io/trellis/docs/user-contributed-extensions/>):

| Extension | URL | Description (verbatim) |
|---|---|---|
| `trellis-db-push-and-pull` | <https://github.com/hamedb89/trellis-db-push-and-pull> | "Push and pull databases with Trellis and Ansible playbooks" |
| `trellis-database-uploads-migration` | <https://github.com/valentinocossar/trellis-database-uploads-migration> | "Ansible playbook for Trellis that manages database and uploads migration" |

Other community tools not on the Roots list:

- `creame/trellis-simple-sync` — Ansible playbooks run via `./bin/sync.sh <environment> <site name> <type> <mode>` where type is `uploads`/`database`/`all` and mode is `push`/`pull`. Takes a timestamped DB backup before each import; uploads sync is additive (never deletes). GUID replacement is skipped by default, toggled with `--extra-vars "skip_guids=false"` ([COMMUNITY], <https://github.com/creame/trellis-simple-sync>).
- `jasperf/trellis-sync` — shell scripts + WP-CLI aliases ([COMMUNITY], <https://github.com/jasperf/trellis-sync>).

### Verdict on `db-sync.sh` and `uploads-sync.sh`

**They are NOT made redundant by trellis-cli.** trellis-cli has no equivalent capability at all, verified at source. Adopting Trellis + trellis-cli would require either keeping the existing scripts, or replacing them with one of the community Ansible playbooks above — which are third-party, unversioned against Trellis releases, and (per the maintainer's own stated reason for rejecting the feature) not designed with multisite in mind.

The one thing trellis-cli *does* provide that is adjacent: `trellis alias` generates WP-CLI aliases for remote environments ([SOURCE], `cmd/alias.go`), which gives you `wp @production db export` style access. That is a building block for a sync script, not a sync feature.

---

## Q3. Trellis + path-based multisite

**Multisite is officially supported and documented**, with a dedicated docs page ([DOC], <https://roots.io/trellis/docs/multisite/>), and "Multisite" appears in the "The Basics" section of the docs sidebar ([DOC], <https://roots.io/trellis/docs/>).

Path-based (subdirectory) multisite is configured by setting `subdomains: false`. Verbatim from the docs:

> `subdomains: false   # Set to true if you're using a subdomain multisite install`

and the corresponding constant:

> `"SUBDOMAIN_INSTALL", false); // Set to true if using subdomains`

Additional multisite settings go in the `env` dictionary, verbatim:

> "You can define the env dictionary for more multisite specific settings such as `DOMAIN_CURRENT_SITE` or `PATH_CURRENT_SITE`" ... "gets merged with Trellis' defaults so you don't need to re-define all properties"

### Caveat, quoted verbatim

The docs carry an explicit warning that applies **specifically to path-based multisite on Bedrock**:

> "You may notice that your network's main site URLs contain `/wp/` before the post's or page's pathnames. This is a problem in WP core which occurs when WordPress is located in a subdirectory"

([DOC], <https://roots.io/trellis/docs/multisite/>.) The docs attribute this to Bedrock issue #250 and recommend the **Multisite Fixes** plugin collection as the workaround.

This is a genuine, first-party-acknowledged rough edge that lands exactly on the project's configuration (Bedrock + path-based multisite). It is a WordPress core issue rather than a Trellis defect, and it requires an extra plugin (`roots/multisite-url-fixer` in the Bedrock ecosystem) regardless of which tool provisions the environment.

---

## Q4. Preview environments — ephemeral per-branch / per-PR (DECISIVE)

### Answer: NO. This does not exist in Trellis or trellis-cli, in any form.

**Verified at source.** A repo-wide grep across all Go and Markdown files at HEAD:

```
grep -rniE "preview|ephemeral|per-branch|review app|pull request" --include='*.go' --include='*.md' .
# (no output)
```

([SOURCE], checkout at `3beef80`, 2026-09-08.) There is no preview-environment code, no such concept in the command registry, and not even a mention in the README.

**Why it is structurally absent, not merely unimplemented.** As established in Q1, an environment in Trellis is a *filesystem fact*: a directory under `group_vars/` containing `wordpress_sites.yml`, discovered by `filepath.Glob("group_vars/*/wordpress_sites.yml")` ([SOURCE], `trellis/trellis.go` line 209), paired with a committed `hosts/<environment>` Ansible inventory file. Creating an environment therefore means writing files into the repo and committing them; destroying one means deleting them. There is no lifecycle API, no dynamic inventory, no allocation of a hostname or a database, and no teardown path. Nothing in the tool models an environment as something with a beginning and an end.

To be precise about what *is* possible: because environment names are arbitrary, you **can** hand-create as many long-lived named environments as you like (`staging2`, `qa`, `uat`), and the community does ([COMMUNITY], <https://discourse.roots.io/t/extending-trellis-to-four-environments/14306>). This is manual, static, and committed — the opposite of ephemeral.

The exact need is a known, unmet community request. A Roots Discourse thread describes it directly: team members working in different branches simultaneously, wanting to test each without contending over who can deploy to the single staging environment ([COMMUNITY], <https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651>).

CI/CD integration exists but is **fixed-target, not dynamic**: `roots/setup-trellis-cli` is a first-party GitHub Action ([DOC], <https://github.com/roots/setup-trellis-cli>), and the docs cover deploying with GitHub Actions ([DOC], <https://roots.io/trellis/docs/deploy-with-github-actions/>). These deploy branch→*named existing environment* (merge to `staging` branch deploys to the `staging` environment). They do not create environments.

### Exactly what was searched

To be transparent about coverage, and per the instruction not to fill gaps from memory:

**Source-level (strongest, and conclusive on its own):**
1. `grep -rniE "preview|ephemeral|per-branch|review app|pull request"` over `--include='*.go' --include='*.md'` in the trellis-cli checkout at `3beef80` — **zero matches**.
2. Full read of the command registry in `main.go` lines 61–215 — no such command exists.
3. Read of environment discovery logic in `trellis/trellis.go` lines 195–235.

**Web searches performed:**
4. `Trellis ephemeral preview environments per-branch per-PR site:discourse.roots.io OR site:github.com/roots` — surfaced the "Deploy to multiple staging environments" and "Extending Trellis to four environments" threads; no feature.

**Searches attempted but not completed** (the tool calls were interrupted before returning, so I am not counting them as evidence either way):
5. `"trellis" roots ephemeral environment "per branch" OR "review app" OR "preview environment" wordpress` — interrupted.
6. Direct fetch of <https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651> for verbatim maintainer answers — interrupted. The thread's existence and general subject are attested by the search result in (4), but **I have not read its contents directly, so any specific maintainer position in that thread is UNVERIFIED.**

Given that (1)–(3) are conclusive source-level evidence over the actual shipping code, the incompleteness of (5)–(6) does not change the answer. But it does mean I cannot report what Roots maintainers have *said* about the feature request, only that the feature is absent from the code.

---

## Q5. DDEV: Bedrock, multisite, WSL2, multiple Vite servers

### Bedrock support — DOCUMENTED, first-party on both sides

Bedrock + DDEV is documented by **both** projects, which is unusual and worth noting:

- Roots' own Bedrock docs have a dedicated page ([DOC], <https://roots.io/bedrock/docs/bedrock-with-ddev/>).
- DDEV's own quickstart covers Bedrock ([DOC], <https://docs.ddev.com/en/stable/users/quickstart/> and its source at <https://github.com/ddev/ddev/blob/main/docs/content/users/quickstart.md>).

The documented setup, verbatim from the Roots page:

```bash
ddev config --project-type=wordpress --docroot=web --create-docroot
ddev composer create roots/bedrock
```

then in `.env`:

```
DB_NAME='db'
DB_USER='db'
DB_PASSWORD='db'
DB_HOST='db'
WP_HOME="${DDEV_PRIMARY_URL}"
WP_SITEURL="${DDEV_PRIMARY_URL}/wp"
```

then `ddev start`.

Note the `${DDEV_PRIMARY_URL}` interpolation — DDEV injects the project URL as an environment variable, so the Bedrock `.env` is portable across machines without hardcoding a hostname.

### WordPress multisite — NOT documented by DDEV

There is **no first-party DDEV documentation page for WordPress multisite**. DDEV has one for Drupal multisite (`ddev/ddev-contrib` recipe, <https://github.com/ddev/ddev-contrib/blob/master/recipes/drupal8-multisite/README.md>) but not for WordPress. This gap is explicitly acknowledged in a DDEV GitHub discussion where a user writes: "Since there's already a section in the docs about Drupal multisite, it would still be great to have the same for WordPress" ([COMMUNITY], <https://github.com/orgs/ddev/discussions/6166>).

That same discussion reports the practical state, which is **worse for path-based than subdomain**: multisite "worked well enough for subdomain installs provided that `additional_hostnames` in `config.yaml` was set to include the wildcard subdomain pattern, though **subdirectory installs required additional Nginx rewrite rules**" ([COMMUNITY], <https://github.com/orgs/ddev/discussions/6166>).

Third-party guides exist and reinforce that subdirectory multisite is the awkward case:

- Kalamuna's guide for subdirectory multisite on DDEV notes they **switched web servers** because "handling subdirectory installs is much easier with `.htaccess` compared to Nginx" ([COMMUNITY], <https://www.kalamuna.com/blog/setup-existing-wordpress-multisite-locally-ddev-bonus-pantheon-support>).
- A Medium tutorial covers DDEV + Bedrock + multisite using `roots/multisite-url-fixer` and custom `application.php` config ([COMMUNITY], <https://medium.com/@DesignerElise/set-up-ddev-bedrock-wordpress-mu-nginx-super-quickly-fae46f6f5f31>).

**Assessment: DDEV + Bedrock is documented. DDEV + Bedrock + path-based multisite is a community workaround requiring nginx rewrite rules or an Apache switch.**

### WSL2 — first-class and documented

Yes, unambiguously. DDEV's installation docs present WSL2 as the primary Windows path. Verbatim, the installation-modes table lists:

> **"Docker CE inside WSL2" (recommended)** — "Most users, best performance."

([DOC], <https://docs.ddev.com/en/stable/users/install/ddev-installation/>.)

Further documented specifics:

- Distro creation: `wsl --install Ubuntu-26.04 --name DDEV`; "The DDEV installer supports Ubuntu and Debian and has been tested with Kali Linux and eLxr" ([DOC], same page).
- **Project placement**: projects belong in the Linux filesystem (e.g. `/home/<username>/projects`), *not* on `/mnt/c`, "for better performance and to avoid permission issues" ([DOC], same page). This matches the same guidance lerd gives (see Q7).
- **Mirrored networking**: with WSL2 "Mirrored" networking mode you must enable `hostAddressLoopback=true`, either via Docker Desktop's *Networking > Host Address Loopback* toggle or in `.wslconfig` with `networkingMode=Mirrored` and `hostAddressLoopback=true` ([DOC], same page).
- DDEV rewrote its WSL2 guide with a new video in 2025 ([DOC/first-party blog], <https://ddev.com/blog/watch-ddev-local-from-scratch-with-windows-wsl2/>).

Third-party corroboration that this is the mainstream path: Drupal.org maintains an official wiki guide for DDEV in WSL2 ([COMMUNITY], <https://www.drupal.org/docs/develop/local-server-setup/windows-development-environment/installing-drupal-with-ddev-in-wsl2-on-windows>).

**This is the sharpest single contrast in this report: DDEV documents WSL2 as its recommended Windows configuration, while trellis-cli's README says it does not support WSL at all (see Q9).**

### Multiple Vite dev servers — partially documented

DDEV has a **first-party Vite integration page** ([DOC], <https://docs.ddev.com/en/stable/users/usage/vite/>). The documented mechanism is `web_extra_exposed_ports` in `.ddev/config.yaml`. Verbatim from that page:

```yaml
web_extra_exposed_ports:
  - name: vite
    container_port: 5173
    http_port: 5172
    https_port: 5173
```

with the matching `vite.config.js`:

```js
server: {
  host: "0.0.0.0",
  port: 5173,
  strictPort: true,
  origin: `${process.env.DDEV_PRIMARY_URL_WITHOUT_PORT}:5173`,
  cors: {
    origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
  },
}
```

**The Vite page itself only ever shows ONE dev server** — every example uses a single instance on 5173.

However, `web_extra_exposed_ports` **is an array and is documented to hold multiple named entries**, on a different page. The "Extending and Customizing Environments" docs describe exposing "a Node.js server running in the web container on port 3000 as `https://<project>.ddev.site:3000` and a 'something' server running in the web container on port 4000 as `https://<project>.ddev.site:4000`, using separate entries each with their own `name`, `container_port`, `http_port`, and `https_port`" ([DOC], <https://docs.ddev.com/en/stable/users/extend/customization-extendibility/>). The config reference confirms `web_extra_exposed_ports` "provides additional named sets of ports to expose via ddev-router" ([DOC], <https://docs.ddev.com/en/stable/users/configuration/config/>).

So a three-Vite-server config would extrapolate to:

```yaml
web_extra_exposed_ports:
  - name: theme-one
    container_port: 5173
    http_port: 5172
    https_port: 5173
  - name: theme-two
    container_port: 5174
    http_port: 5175
    https_port: 5174
  - name: plugin
    container_port: 5175
    http_port: 5176
    https_port: 5175
```

**Grading this honestly: the multi-port *mechanism* is documented; three concurrent Vite servers specifically is an undocumented extrapolation.** I found no first-party example of multiple simultaneous Vite/dev servers in one DDEV project. Rate it **documented mechanism / undocumented application**.

Two documented caveats that bear on this:

1. `web_extra_exposed_ports` "can't forward ports for non-HTTP TCP or UDP daemons" — those need a separate docker-compose file ([DOC], <https://docs.ddev.com/en/stable/users/extend/customization-extendibility/>). Vite dev servers are HTTP, so this is fine; the HMR websocket rides the same HTTP origin.
2. "**if multiple projects declare the same port, only the first project will be able to start successfully**" ([DOC], same page). This is a **cross-project** collision constraint and is directly relevant to the "multiple simultaneous feature stagings" row in Q9: running the same project twice (e.g. two branches as two DDEV projects) with the same fixed Vite ports would collide, and `strictPort: true` in the documented Vite config means the second would fail rather than drift to a free port.
3. A `ddev restart` is required after changing `.ddev/config.yaml`; verify with `ddev describe` ([DOC], same page).

Also relevant: when using `web_extra_exposed_ports` in `.ddev/config.yaml`, a `.ddev/docker-compose.*.yaml` for port exposure is not required ([DOC], <https://docs.ddev.com/en/stable/users/usage/vite/>).

---

## Q6. DDEV + Trellis together

**There is no first-party documentation for using DDEV and Trellis as a combined local+remote pair.** I found no Roots or DDEV page describing that specific pairing end to end. Grading everything below accordingly.

What *is* first-party documented is the enabling half of it: Roots explicitly supports **using Trellis only for deployment while using a different local environment**. The Bedrock local-development docs list Trellis, Laravel Valet, Local, DDEV, Lando and DevKinsta as local options ([DOC], <https://roots.io/bedrock/docs/local-development/>), and Roots separately documents Bedrock-with-DDEV as a supported local setup ([DOC], <https://roots.io/bedrock/docs/bedrock-with-ddev/>).

So the combination is best described as: **two independently-documented halves that share Bedrock as the common artifact, with no documented integration between them.** They do not conflict architecturally, because they operate at different layers:

- DDEV = local containerised dev environment.
- Trellis = Ansible provisioning + deploy for staging/production.

The friction people report is practical rather than architectural — historically, running Vagrant/VirtualBox for Trellis local alongside Docker for DDEV meant two virtualisation stacks competing for resources. With Trellis now on Lima/QEMU (Q1) the same tension persists in a different form on Linux, since Lima-on-Linux uses QEMU/KVM while DDEV uses Docker.

**Important caveat on the above:** the characterisation of the DDEV+Trellis relationship in this section is drawn substantially from a web search summary rather than from pages I fetched and read individually. The two Roots doc URLs are verified first-party. The claims about *why* people avoid running both locally, and about Trellis being commonly used with Kinsta/RunCloud, come from aggregated search results — treat as **[COMMUNITY], weakly sourced**. I did not independently verify them page by page.

**Practical bottom line, stated as a gap rather than a recommendation:** nothing in either project's documentation describes keeping a DDEV local config and a Trellis `group_vars` tree in sync — they are two separate configuration languages describing the same stack. PHP version, extensions, and service versions are declared once in `.ddev/config.yaml` and again in Trellis's `group_vars`, with no shared source of truth. This is precisely the "one config describes local and remote" gap named in the project's proposed wedge.

---

## Q7. lerd (local checkout at `/home/yevhenii/www/lerd`)

### Multisite — NOT SUPPORTED, not even mentioned

```
grep -rniE "multisite" docs/ internal/ embed/ README.md
# (no output)
```

([SOURCE], run in `/home/yevhenii/www/lerd`.) **Zero occurrences of "multisite" anywhere in lerd's documentation, internal Go source, or embedded assets.**

### The WordPress framework definition

lerd is framework-agnostic by design: framework knowledge lives as YAML in a separate store, not in the Go binary. Confirmed — there is no WordPress YAML in the lerd repo itself (`find` for `*wordpress*` returns only `docs/getting-started/wordpress.md`). The definitions live at <https://github.com/lerd-env/frameworks>.

The store contains `frameworks/wordpress/5.yaml`, `6.yaml`, `7.yaml` ([SOURCE], `gh api repos/lerd-env/frameworks/contents/frameworks/wordpress`).

Verbatim excerpts from `frameworks/wordpress/7.yaml` (<https://raw.githubusercontent.com/lerd-env/frameworks/main/frameworks/wordpress/7.yaml>):

```yaml
name: wordpress
version: "7"
label: WordPress
public_dir: .
create: composer create-project --no-install --no-plugins --no-scripts johnpbloch/wordpress-core:^7.0

detect:
  - file: wp-login.php
    version_file: wp-includes/version.php
    version_pattern: "\\$wp_version\\s*=\\s*'([^']+)'"
  - file: wp-config.php

env:
  fallback_file: wp-config.php
  fallback_format: php-const
  app_file: wp-config.php
  app_format: php-const
  example_file: wp-config-sample.php
  url_key: WP_HOME
```

**Three findings from this, all significant for a Bedrock project:**

1. **No multisite.** The word does not appear in the definition.
2. **`public_dir: .`** — the document root is the project root. **Bedrock serves from `web/`.** A Bedrock project would be mis-rooted by default.
3. **The env model is `wp-config.php` PHP constants, not Bedrock's `.env`.** `app_format: php-const`, `app_file: wp-config.php`. **Bedrock's entire premise is a dotenv file and `config/application.php`.** lerd's WordPress definition writes `define('DB_NAME', ...)` into `wp-config.php` — the walkthrough confirms this explicitly: "WordPress keeps its configuration as PHP constants rather than in a `.env`, so lerd reads and writes `wp-config.php` directly" ([SOURCE], `/home/yevhenii/www/lerd/docs/getting-started/wordpress.md` line 80).
4. **Scaffolding is `johnpbloch/wordpress-core`, not `roots/bedrock`.**

There is **no `bedrock` entry** in the framework index — the full list is laravel, lumen, symfony, wordpress, drupal, typo3, cakephp, codeigniter, statamic, magento, tempest ([SOURCE], `frameworks/index.json`).

**Assessment: lerd does not model Bedrock, and does not model multisite at all.** A Bedrock project would need a user overlay adjusting `public_dir` and the env format, and multisite would be entirely unaddressed.

### Vite dev servers — can ONE site have multiple? Answer: not per site; yes per worktree

This is a subtle and important distinction.

**Workers are keyed by name within a framework definition.** A framework declares a `workers:` map, and Vite is conventionally a single entry named `vite` ([SOURCE], `/home/yevhenii/www/lerd/docs/usage/framework-workers.md` lines 104–113):

```yaml
workers:
  vite:
    label: Vite
    command: npm run dev
    restart: on-failure
    host: true
    check:
      file: vite.config.js
```

Systemd unit names are derived as `lerd-<worker>-<site>[-<branch>]` ([SOURCE], same file, line 119 and line 237). **Because the unit name is keyed on `(worker-name, site, branch)`, one site on one branch can have exactly one worker called `vite`.**

**However — multiple *differently named* workers can each run a dev server, and lerd explicitly supports this.** Verbatim ([SOURCE], `docs/usage/framework-workers.md` line 71):

> "Every worker that declares a proxy gets its own locations, so an asset server and a websocket server run side by side on the same site rather than the first one declared taking it."

And project-specific workers can be added freely via `custom_workers` in `.lerd.yaml` ([SOURCE], same file, lines 181–211). So three Vite servers **could** be modelled as three custom workers (`vite-theme-a`, `vite-theme-b`, `vite-plugin`), each with its own `proxy` block and its own pinned port.

Two caveats on that path:

- Port assignment: `port: pinned` makes lerd own the port and keep it "clear of every other site's pinned ports and dev servers" ([SOURCE], line 75). So collisions are handled — but lerd assigns the ports, rather than honouring fixed 5173/5174/5175.
- The "dev servers on the site's own domain" integration explicitly **only qualifies for a worker whose command starts the tool directly**: "A command that only reaches the tool through a runner such as `concurrently` is left alone, since the flags lerd appends would land on the wrong process" ([SOURCE], line 157). Three separate workers each running their own `npm run dev:theme-a` would each qualify; a single `concurrently`-based command would not.

**Where lerd genuinely shines, and it is directly adjacent to the proposed wedge: multiple Vite instances across git worktrees.** Verbatim ([SOURCE], `docs/features/git-worktrees.md` line 38):

> "Multiple worktrees can run Vite simultaneously, each gets its own unit (`lerd-vite-<site>-<branch>`) and Vite auto-increments ports."

and ([SOURCE], `docs/usage/framework-workers.md` line 119):

> "when a worktree is created, with per-worktree units (`lerd-vite-<site>-<branch>`, supervised by systemd on Linux and launchd on macOS) so multiple Vite instances can run simultaneously with auto-incremented ports."

**Summary for Q7's question as asked:** one site + one branch = one worker named `vite`. But (a) multiple distinctly-named dev-server workers on one site are supported, and (b) one Vite per worktree, many worktrees in parallel, is a documented first-class feature.

### lerd's per-branch model is the closest analogue to preview environments found in this survey

lerd treats "every git worktree as a first-class scope: each branch checkout gets its own subdomain, its own optional database, its own PHP and Node versions, and its own slot in the dashboard" ([SOURCE], `docs/features/git-worktrees.md` line 3). Concretely, per branch it provides:

- Its own subdomain: `feature-auth.myapp.test`, with wildcard TLS SANs inherited from the parent's mkcert cert (lines 103–112).
- **Its own database**, with a choice of empty / clone-from-main / clone-from-another-worktree, via `mysqldump --single-transaction` piped into the new schema (lines 202–219).
- Env-file rewriting with templated overrides — `{{domain}}`, `{{branch}}`, `{{parent}}`, `{{scheme}}` (lines 116–140).
- Automatic dependency install, vhost generation, and teardown ordering on removal (lines 55–76, 233–248).
- **Notably, WordPress is explicitly handled in the worktree env-sync path**: "the PHP-config frameworks are seeded through their own writers: WordPress's `wp-config.php` (rewriting `WP_HOME`)" (line 62).

**This is per-branch environment management — but strictly LOCAL.** It is worktrees on one developer's machine, not deployed environments a stakeholder can visit. lerd is a local development environment; it has no deploy, no provisioning, and no remote environment concept.

### WSL2 documented as beta — exact lines

Confirmed, multiple places. Verbatim quotes with file paths:

**`/home/yevhenii/www/lerd/docs/getting-started/wsl2.md` line 8:**

> "Windows support via WSL2 is **beta**. The standard Linux build runs unchanged inside a systemd WSL2 session and is fine for daily development, but this path gets less testing than native Linux or macOS and a couple of steps still need manual Windows-side setup (mirrored networking and trusting the mkcert root CA, both below). Please report anything that misbehaves on the [issue tracker](https://github.com/lerd-env/lerd/issues)."

**`/home/yevhenii/www/lerd/docs/getting-started/installation.md` line 304:**

> "There is no native Windows build. Lerd runs on Windows through WSL2, where the standard Linux build works unchanged once systemd and rootless Podman are set up. Windows support is **beta**, it works well for daily development but gets less testing than native Linux or macOS."

**`/home/yevhenii/www/lerd/docs/.vitepress/config.ts` line 140** — the nav label itself:

> `{ text: 'Windows (WSL2, beta)', link: '/getting-started/wsl2' }`

**`/home/yevhenii/www/lerd/docs/getting-started/sail.md` line 103** — comparison table row:

> `| Platforms | Linux (systemd), macOS, Windows via WSL2 (beta) | Linux, macOS, Windows |`

Requirements, verbatim (`docs/getting-started/wsl2.md` line 12): "Ubuntu 22.04 or newer, or Debian 12 or newer, running on WSL 0.67.6 or newer (the release that brought official systemd support)." **Ubuntu 22.04 meets this.**

There is a `lerd wsl:setup` command that automates the setup: "enabling systemd in `/etc/wsl.conf`, setting the Podman `events_logger`, turning on mirrored networking in your Windows `.wslconfig`, importing the mkcert root CA into the Windows trust store, and masking the tray" (line 23), and `lerd doctor` adds a `[WSL2]` section (line 25).

Two documented WSL2 limitations: the system tray does not work ("`lerd-tray` needs a graphical tray host implementing the `StatusNotifierItem` or `AppIndicator` protocol, and WSL2 does not provide one", line 169), and `/mnt/c` projects are slow via 9P — "roughly an order of magnitude slower than the WSL2 ext4 filesystem" (line 133), same guidance DDEV gives.

lerd is also **rootless Podman only**: "Lerd is built exclusively for rootless Podman with Quadlet. Installing `docker.io` or `docker-ce` on the same WSL distro causes networking and cgroup conflicts" (line 108). That is a hard conflict with DDEV, which requires Docker.

---

## Q8. Coolify: preview deployments and stateful WordPress

### Preview deployments exist and are documented

Verbatim from the docs:

> "Preview deployments allow Coolify to automatically deploy new versions of your application whenever someone opens a pull request (PR) on your GitHub repository."

> "These preview deployments are automatically deleted once the associated pull request is merged or closed, ensuring a clean environment."

([DOC], <https://coolify.io/docs/applications/ci-cd/github/preview-deploy>.)

Each preview gets its own URL from a customisable template using `{{random}}` or `{{pr_id}}` ([DOC], same page).

Environment variables are isolated: production variables "are never exposed to preview deployments triggered from pull requests", with a separate variable set for PR previews ([DOC], same page). The newer docs add: "Each preview can use its own domain and environment variables without replacing the production deployment" ([DOC], <https://next.coolify.io/docs/applications/deployments/preview-deployments>).

### Does each preview get its own database? NO — not automatically

**This is the decisive limitation for a stateful app like WordPress.**

The preview-deploy documentation **does not state that a database is provisioned per preview**. What it does say, verbatim from the newer docs, is a warning that implies the opposite — that previews talk to *external, persistent* services:

> "use non-production credentials for preview databases, storage, and external services, since a preview container is temporary, but data written to an external service can remain after Coolify deletes the preview"

([DOC], <https://next.coolify.io/docs/applications/deployments/preview-deployments>.)

That is guidance to point previews at a *separate pre-existing* database with non-production credentials — not a statement that Coolify creates one per PR. The lifecycle is explicit and asymmetric: **the container is ephemeral, the data store is not.**

Community reports confirm per-PR databases must be built by hand, and that it is awkward:

- A user's workaround uses "the Pre Deployment Commands function to create a new database", using `SERVICE_USER_DB` / `SERVICE_PASSWORD_DB` ([COMMUNITY], <https://github.com/coollabsio/coolify/discussions/4708>).
- The same thread reports a structural problem: "when manually deploying a pull request, the docker compose file that is generated changes the container names (`db-pr-295`, `frontend-pr-295` etc.), which breaks the connection to the database and nginx config since it relies on the original container names" ([COMMUNITY], same).
- Another: "if with docker compose you use a service name to connect to the database, it's hard to make it work knowing that preview deployment changes service names", with one user concluding the feature "is not really ready unless you have a pretty simple setup" ([COMMUNITY], same).
- Achieving true per-preview database isolation is documented by a **third party** via DBLab database branching, driven through the Coolify API with explicit clone-creation and clone-deletion steps ([COMMUNITY], <https://postgres.ai/docs/dblab-howtos/branching/preview-environments-with-dblab-and-coolify>). Note this is a PostgreSQL branching tool; the project in question uses MariaDB.

**Additional gap specific to WordPress, verified by absence:** even granting a per-PR database, nothing in Coolify's preview feature performs a **WordPress search-replace of the site URL**. A WordPress database cloned from production contains absolute production URLs in `wp_options` (`siteurl`, `home`) and serialised data throughout `wp_postmeta`. Dropped onto a preview hostname such as `pr-42.example.com`, the site redirects to production. For **path-based multisite** this is worse: `wp_blogs` and `wp_site` carry per-subsite domain and path rows that all need rewriting. I found no Coolify documentation addressing WordPress URL rewriting; this is **absent, not merely undocumented**.

**Assessment: Coolify's preview deployments are designed for stateless application containers.** They are usable for a stateful WordPress app only with substantial custom scripting for (a) per-PR database creation, (b) seeding it from a dump, and (c) search-replace of domains — none of which Coolify provides.

**UNVERIFIED:** I did not verify whether Coolify supports per-preview **persistent volume** provisioning (relevant to `wp-content/uploads`). I fetched the two preview-deployment doc pages above; neither addressed volumes. I did not separately fetch Coolify's storage/persistent-volume documentation.

---

## Q9. Verdict matrix

Columns are the five tools. Cells: **documented** / **community workaround** / **not supported** / **unverified**.

| Capability | Trellis | trellis-cli | DDEV | lerd | Coolify |
|---|---|---|---|---|---|
| **Fast local setup** | not supported on WSL2 [a] | not supported on WSL2 [a] | **documented** — WSL2 "recommended", "best performance" [b] | **documented (beta on WSL2)** [c] | not supported — remote PaaS, not a local env [d] |
| **Path-based multisite** | **documented, with caveat** [e] | n/a (config lives in Trellis) | community workaround — needs nginx rewrites [f] | **not supported** — zero mentions [g] | not supported — no WordPress-aware layer |
| **3 concurrent Vite servers** | not supported — no dev-server concept | not supported | **documented mechanism**, undocumented at 3× [h] | community workaround via `custom_workers`; per-worktree Vite is documented [i] | not supported — build-time, not dev-server |
| **Staging env** | **documented** — `group_vars/staging` ships [j] | **documented** — `trellis deploy staging` | not supported — local only | not supported — local only | **documented** |
| **MULTIPLE simultaneous feature stagings** | community workaround — hand-created static envs [k] | not supported — no lifecycle [k] | not supported remotely; local multi-project hits port collisions [l] | **local only** — per-worktree, not deployed [m] | **documented but not for stateful apps** [n] |
| **Production env** | **documented** [j] | **documented** — `trellis deploy production` | not supported | not supported | **documented** |
| **DB pull/push with search-replace** | **not supported** — explicitly rejected upstream [o] | **not supported** — verified at source [o] | not supported [p] | not supported — `db:import` only [q] | not supported [r] |
| **Uploads sync** | **not supported** [o] | **not supported** — zero `rsync`/`uploads` in source [o] | not supported | not supported | not supported |
| **CI/CD** | **documented** — GitHub Actions [s] | **documented** — `setup-trellis-cli` action [s] | community workaround [t] | not supported — local dev tool | **documented** — git-push driven |
| **WSL2 support** | **not supported** [a] | **not supported** — README says so verbatim [a] | **documented — recommended path** [b] | **documented as beta** [c] | n/a — server-side [d] |

### Footnotes

**[a]** trellis-cli README, verbatim: **"> **Warning**: trellis-cli does not currently support Windows or WSL."** and "For Windows and WSL support, see the community-maintained fork at [qwatts-dev/trellis-cli](https://github.com/qwatts-dev/trellis-cli)." ([SOURCE], `/home/yevhenii/www/trellis-cli/README.md` lines 72–76, at commit `3beef80`, 2026-09-08.) Compounding this: local VMs require QEMU/KVM on Linux ([SOURCE], `cmd/vm_start.go` line 113), and the code warns `"Warning: KVM is not available (/dev/kvm not found). QEMU will run without hardware acceleration and will be very slow."` ([SOURCE], `pkg/lima/manager.go` line 491) — nested virtualisation under WSL2 is the exact scenario this warns about. **I did not test whether trellis-cli in fact runs under WSL2; the grading reflects the project's own stated support position, which is the relevant fact for tooling decisions.**

**[b]** DDEV docs list "Docker CE inside WSL2" as **(recommended)**, "Most users, best performance" ([DOC], <https://docs.ddev.com/en/stable/users/install/ddev-installation/>).

**[c]** "Windows support via WSL2 is **beta**" ([SOURCE], `/home/yevhenii/www/lerd/docs/getting-started/wsl2.md` line 8). Requires Ubuntu 22.04+ (met) and WSL 0.67.6+. Rootless Podman only — conflicts with Docker on the same distro (line 108).

**[d]** Coolify is a self-hosted deployment platform, not a local development environment. Not applicable rather than deficient.

**[e]** `subdomains: false` for path-based ([DOC], <https://roots.io/trellis/docs/multisite/>). Caveat quoted verbatim in Q3: main-site URLs contain `/wp/`, a WP-core problem when WordPress is in a subdirectory; needs the Multisite Fixes plugins.

**[f]** Subdirectory multisite "required additional Nginx rewrite rules" ([COMMUNITY], <https://github.com/orgs/ddev/discussions/6166>); Kalamuna switched to Apache because "handling subdirectory installs is much easier with `.htaccess` compared to Nginx" ([COMMUNITY], <https://www.kalamuna.com/blog/setup-existing-wordpress-multisite-locally-ddev-bonus-pantheon-support>). No first-party DDEV WordPress-multisite page exists.

**[g]** `grep -rniE "multisite" docs/ internal/ embed/ README.md` in the lerd checkout returns **zero matches**; `frameworks/wordpress/7.yaml` contains no multisite concept.

**[h]** `web_extra_exposed_ports` is documented as an array of named port sets, with a two-server example ([DOC], <https://docs.ddev.com/en/stable/users/extend/customization-extendibility/>), but the Vite page shows only a single server ([DOC], <https://docs.ddev.com/en/stable/users/usage/vite/>). Three Vite servers is a reasonable extrapolation of a documented mechanism, not a documented configuration.

**[i]** One worker name per site per branch, so three servers means three distinct `custom_workers` entries with their own proxy blocks — supported ("an asset server and a websocket server run side by side on the same site", `docs/usage/framework-workers.md` line 71) but not documented for Vite×3. Ports are lerd-assigned, not fixed at 5173/5174/5175. Per-worktree Vite *is* documented (line 119).

**[j]** `group_vars/` ships `all`, `development`, `production`, `staging` ([SOURCE], roots/trellis repo contents).

**[k]** Environment names are arbitrary — discovered by `filepath.Glob("group_vars/*/wordpress_sites.yml")` ([SOURCE], `trellis/trellis.go` line 209) — so N static environments can be hand-created and committed ([COMMUNITY], <https://discourse.roots.io/t/extending-trellis-to-four-environments/14306>). But each requires a committed directory, a `hosts/` inventory, and a provisioned server. There is no create/destroy lifecycle. The need is a known unmet request ([COMMUNITY], <https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651>).

**[l]** DDEV runs many projects concurrently, but "if multiple projects declare the same port, only the first project will be able to start successfully" ([DOC], <https://docs.ddev.com/en/stable/users/extend/customization-extendibility/>), and the documented Vite config uses `strictPort: true` — so two branches of the same project would collide on Vite ports without per-branch config edits.

**[m]** Per-worktree subdomain + isolated DB (empty / cloned) + env rewriting + teardown, all documented ([SOURCE], `docs/features/git-worktrees.md`). Entirely local; nothing is deployed or shareable beyond `lerd share` tunnels.

**[n]** Auto-deployed per PR, auto-deleted on merge/close ([DOC], <https://coolify.io/docs/applications/ci-cd/github/preview-deploy>), but no automatic per-preview database (Q8), and no WordPress URL rewriting.

**[o]** Verified at source: `db` namespace has only `db open` ([SOURCE], `main.go` lines 68–76); `grep -rniE "search-replace|search_replace|uploads|rsync|\"db push\"|\"db pull\"" --include='*.go' .` returns **zero** non-test matches. Roots deliberately declined to build it in, maintainer `retlehs` verbatim: "the issue with putting something like this directly into trellis is that it's going to have to be opinionated. it's hard to try to account for different types of setups - such as multisite, media libraries that are on CDNs, etc." ([COMMUNITY, maintainer], <https://github.com/roots/trellis/issues/654>). Officially-linked third-party alternatives: `trellis-db-push-and-pull`, `trellis-database-uploads-migration` ([DOC listing], <https://roots.io/trellis/docs/user-contributed-extensions/>), plus `creame/trellis-simple-sync`. An `rsync` command remains an open request ([COMMUNITY], <https://github.com/roots/trellis-cli/issues/109>).

**[p]** DDEV has `ddev import-db` / `ddev export-db` for local import/export (widely used; **I did not fetch the database-management doc page to quote it, so treat the exact command names as UNVERIFIED**). Regardless, DDEV has no concept of remote staging/production environments to push or pull *between*, and no WordPress search-replace.

**[q]** lerd documents `lerd db:import` "to load a production dump" and `lerd db:shell` ([SOURCE], `docs/getting-started/wordpress.md` line 157). This is local import of a file you obtained yourself — not environment-to-environment sync, and no search-replace. lerd *does* clone databases between worktrees via `mysqldump --single-transaction` ([SOURCE], `docs/features/git-worktrees.md` line 211), but locally only.

**[r]** No Coolify documentation found for WordPress-aware database migration or search-replace.

**[s]** `roots/setup-trellis-cli` GitHub Action ([DOC], <https://github.com/roots/setup-trellis-cli>) and a deploy guide ([DOC], <https://roots.io/trellis/docs/deploy-with-github-actions/>). Deploys to fixed named environments.

**[t]** DDEV in CI is common practice but I found no first-party CI/CD documentation page during this research. **UNVERIFIED** whether such a page exists — I did not search for it specifically.

---

## WHAT NOTHING COVERS

Capabilities for which **no tool among these five** provides first-party documented support:

1. **Ephemeral per-branch/per-PR environments for a stateful WordPress app, end to end.** Coolify creates and destroys preview containers but not their databases (Q8). lerd creates and destroys per-branch environments *with* isolated databases but only on localhost (Q7). Trellis has durable remote environments but no lifecycle at all (Q4). **No tool combines: on-demand creation + isolated database seeded from production + WordPress URL rewriting + a shareable URL + automatic teardown.**

2. **WordPress-aware database transfer between environments as a first-party feature.** Trellis explicitly rejected it as too opinionated ([COMMUNITY, maintainer], roots/trellis#654); trellis-cli has zero source references to it; DDEV has no remote environments; lerd is local-only; Coolify has no WordPress layer. This capability exists **only** as community Ansible playbooks and hand-written scripts — which is precisely what `deploy/db-sync.sh` already is.

3. **Search-replace of domains as part of an environment lifecycle.** Every tool that moves a WordPress database in this survey delegates search-replace to the user or to a community playbook. For **path-based multisite** — where `wp_blogs` and `wp_site` carry per-subsite domain/path rows — no tool addresses it at all.

4. **One configuration describing both local and remote.** DDEV describes local (`.ddev/config.yaml`); Trellis describes remote (`group_vars/`). PHP version, extensions, and service versions must be declared twice with no shared source of truth and no drift detection (Q6). lerd describes local only (`.lerd.yaml`); Coolify describes remote only.

5. **Path-based multisite as a documented first-class case.** Trellis documents it but ships a known `/wp/` URL defect requiring an extra plugin (Q3). DDEV requires nginx rewrites or an Apache switch (Q5). lerd does not model it at all (Q7). Coolify has no concept of it.

6. **Multiple simultaneous *deployed* feature environments.** Trellis can host N static hand-built environments but cannot create them on demand; DDEV and lerd are local; Coolify's are stateless. Nobody offers N concurrent, deployed, stateful WordPress environments with sane data.

7. **Three fixed-port concurrent Vite dev servers as a documented configuration.** DDEV documents the port-exposure mechanism but never three dev servers, and `strictPort: true` plus cross-project port collisions complicate running two branches at once (Q5). lerd supports multiple dev-server workers but assigns ports itself rather than honouring 5173/5174/5175 (Q7).

8. **A single tool covering the target platform (WSL2/Ubuntu 22.04) across local *and* remote.** trellis-cli's README states outright that WSL is unsupported ([SOURCE], README line 74) — which removes the leading remote-side tool from the platform entirely, in its own words. DDEV and lerd both support WSL2 but are local-only.

---

## Implications

Stated neutrally, as consequences of the findings rather than recommendations.

- **The two homegrown scripts are not replaceable by trellis-cli.** `deploy/db-sync.sh` and `deploy/uploads-sync.sh` have no counterpart in trellis-cli at HEAD, verified at source. Adopting Trellis means keeping them, or swapping them for third-party Ansible playbooks of comparable maturity. `deploy/deploy.sh` **is** genuinely replaceable — `trellis deploy` plus `trellis rollback` cover rsync+SSH deployment with zero-downtime releases and rollback, which the shell script likely does not.

- **The platform constraint is the sharpest single finding.** trellis-cli's own README states it does not support WSL. That is not a performance caveat or a rough edge; it is the project declining to support the environment the developer works in. The named community fork (`qwatts-dev/trellis-cli`) is unaudited here. Any Trellis-based plan must resolve this first.

- **Trellis's environment model is more flexible than commonly assumed, and less flexible than needed.** Arbitrary environment names work by construction (`filepath.Glob` over `group_vars/*/`). But an environment is a committed directory plus a provisioned server, so "many static environments" is achievable and "environments that come and go with branches" is not.

- **The preview-environment gap is real and is the least-covered capability surveyed.** Two tools solve adjacent halves: lerd does per-branch isolated environments with databases, locally; Coolify does per-PR deploy and teardown, statelessly. The union of those halves — for a stateful, multisite WordPress app — is documented by nobody.

- **Bedrock and multisite are where tool support thins out fastest.** lerd's WordPress definition assumes `public_dir: .` and `wp-config.php` PHP constants, both of which Bedrock contradicts, and it has no multisite concept whatsoever. DDEV handles Bedrock well (first-party docs on both sides) but path-based multisite only via community nginx workarounds. Trellis handles both but ships a documented `/wp/` URL defect.

- **The "one config for local and remote" premise is not currently met by any pairing.** A DDEV+Trellis combination — the most conventional route — means maintaining two independent descriptions of the same stack with no synchronisation and no drift detection.

- **Re-verification is warranted.** Trellis moved from Vagrant to Lima, gained Linux VM support after issue #599, and replaced `droplet create` with `server create`, all within roughly the last year. The docs already lag the source in places (the CLI docs page omits `server dns`, `venv hook`, and the deprecation marker on `droplet`). Prefer reading source over docs for this ecosystem.

---

## Sources

**Primary-source checkouts and repositories:**

- `/home/yevhenii/www/trellis-cli` @ `3beef80ad6532d2482d1bef551b778b4cd083ac3` (2026-09-08, `v1.19.0-44-g3beef80`) — `main.go`, `README.md`, `cmd/vm.go`, `cmd/vm_start.go`, `cmd/db_open.go`, `trellis/trellis.go`, `pkg/lima/manager.go`
- `/home/yevhenii/www/lerd` — `docs/getting-started/wordpress.md`, `docs/getting-started/wsl2.md`, `docs/getting-started/installation.md`, `docs/getting-started/sail.md`, `docs/usage/framework-workers.md`, `docs/features/git-worktrees.md`, `docs/.vitepress/config.ts`
- <https://github.com/lerd-env/frameworks> — `frameworks/index.json`, `frameworks/wordpress/7.yaml`
- <https://github.com/roots/trellis> — `group_vars/` directory listing

**Trellis / Roots (first-party docs):**

- <https://roots.io/trellis/docs/>
- <https://roots.io/trellis/docs/cli/>
- <https://roots.io/trellis/docs/remote-server-setup/>
- <https://roots.io/trellis/docs/multisite/>
- <https://roots.io/trellis/docs/database-access/>
- <https://roots.io/trellis/docs/local-development/>
- <https://roots.io/trellis/docs/deploy-to-hetzner-cloud/>
- <https://roots.io/trellis/docs/deploy-with-github-actions/>
- <https://roots.io/trellis/docs/user-contributed-extensions/>
- <https://roots.io/guides/using-trellis-to-provision-and-deploy-to-digitalocean-droplets/>
- <https://roots.io/bedrock/docs/local-development/>
- <https://roots.io/bedrock/docs/bedrock-with-ddev/>
- <https://roots.io/whats-new-in-trellis-december-2025/>
- <https://roots.io/trellis-drops-vagrant-support-in-favor-of-lima-vms/>
- <https://roots.io/introducing-lima-to-trellis-for-faster-local-development/>
- <https://github.com/roots/trellis-cli>
- <https://github.com/roots/setup-trellis-cli>

**Trellis (community / issues):**

- <https://github.com/roots/trellis/issues/654>
- <https://github.com/roots/trellis-cli/issues/109>
- <https://github.com/roots/trellis-cli/issues/599>
- <https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651>
- <https://discourse.roots.io/t/extending-trellis-to-four-environments/14306>
- <https://github.com/hamedb89/trellis-db-push-and-pull>
- <https://github.com/valentinocossar/trellis-database-uploads-migration>
- <https://github.com/creame/trellis-simple-sync>
- <https://github.com/jasperf/trellis-sync>
- <https://github.com/qwatts-dev/trellis-cli>

**DDEV:**

- <https://docs.ddev.com/en/stable/users/install/ddev-installation/>
- <https://docs.ddev.com/en/stable/users/quickstart/>
- <https://docs.ddev.com/en/stable/users/usage/vite/>
- <https://docs.ddev.com/en/stable/users/extend/customization-extendibility/>
- <https://docs.ddev.com/en/stable/users/configuration/config/>
- <https://github.com/ddev/ddev/blob/main/docs/content/users/quickstart.md>
- <https://ddev.com/blog/watch-ddev-local-from-scratch-with-windows-wsl2/>
- <https://github.com/orgs/ddev/discussions/6166>
- <https://github.com/ddev/ddev-contrib/blob/master/recipes/drupal8-multisite/README.md>
- <https://www.kalamuna.com/blog/setup-existing-wordpress-multisite-locally-ddev-bonus-pantheon-support>
- <https://medium.com/@DesignerElise/set-up-ddev-bedrock-wordpress-mu-nginx-super-quickly-fae46f6f5f31>
- <https://www.drupal.org/docs/develop/local-server-setup/windows-development-environment/installing-drupal-with-ddev-in-wsl2-on-windows>

**lerd:**

- <https://github.com/lerd-env/lerd>
- <https://github.com/lerd-env/frameworks>
- <https://lerd.sh/usage/sites>

**Coolify:**

- <https://coolify.io/docs/applications/ci-cd/github/preview-deploy>
- <https://next.coolify.io/docs/applications/deployments/preview-deployments>
- <https://github.com/coollabsio/coolify/discussions/4708>
- <https://postgres.ai/docs/dblab-howtos/branching/preview-environments-with-dblab-and-coolify>
