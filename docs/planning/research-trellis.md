# Trellis / Trellis CLI Research

Research date: 2026-09-10 — findings should be re-verified in ~3 months as Trellis/trellis-cli evolve.

Context: WordPress Bedrock 6.9.4, path-based multisite, PHP 8.3, MariaDB, Redis, three parallel Vite dev servers. Existing homegrown `deploy/deploy.sh` (rsync+SSH), `deploy/db-sync.sh` (DB pull/push + WP search-replace of domains), `deploy/uploads-sync.sh`.

## Q1. Trellis Provisioning & Environments

**Bare VPS provisioning:** Documented. Trellis provisions plain Ubuntu servers on any host. From https://roots.io/trellis/docs/remote-server-setup/ (via WebFetch): "Trellis servers can be run on _any_ hosting platform; traditional dedicated server hosting or cloud platforms. All Trellis needs is a server running a plain Ubuntu operating system." Official guides exist for DigitalOcean and Hetzner Cloud (referenced from the same docs section, "Deploy to DigitalOcean" / "Deploy to Hetzner Cloud"). This confirms it works against arbitrary SSH hosts, not a specific managed platform.

**Environments modeled out of the box:** development, staging (optional), production. From https://roots.io/trellis/docs/ansible/ : "For most Trellis projects, this list of hosts is usually one development virtual machine, one staging server (optional), and one production server." The doc also references the standard inventory files `development`, `staging`, `production`.

**Per-environment config / group_vars structure:** Documented at https://roots.io/trellis/docs/ansible/ . Quote: "'group' naming isn't the most clear, but as shown above, these refer to Ansible's concept of 'inventory groups'. And since Trellis' inventory hosts are named for environments, 'group vars' are really just _environment specific variables_." Config lives under `group_vars/<environment>/` (e.g. `group_vars/development/wordpress_sites.yml`, `group_vars/production/wordpress_sites.yml`, `group_vars/<env>/vault.yml` for secrets).

**Inheritance from group_vars/all:** Documented. Quote from https://roots.io/trellis/docs/ansible/ : "Note: the `all` group (in `group_vars/all`) is special and applies to all groups." `group_vars/all/vault.yml` and `group_vars/all/main.yml` exist in the Trellis repo (https://github.com/roots/trellis/blob/master/group_vars/all/vault.yml, https://github.com/roots/trellis/blob/master/group_vars/all/main.yml) as the shared base that environment-specific files override via normal Ansible variable precedence.

**Vagrant status — Vagrant is NOT the current documented local dev path.** The local development docs (https://roots.io/trellis/docs/local-development/, fetched) do not mention Vagrant at all. Current official tooling: "Trellis has an official integration with Lima for development environments using virtual machines." Running `trellis vm start` (Trellis CLI) provisions a Lima VM and runs WordPress in roughly 5 minutes per that page. The same page also documents two alternatives: Laravel Valet ("lighter-weight alternative", with an explicit caution that it doesn't guarantee dev/prod parity), and using Trellis with no local environment at all (production-only use). Given Vagrant is absent from current official docs and Lima is presented as the official integration, Vagrant appears superseded in current documentation — this is based on the current state of the docs page only; no explicit "Vagrant is deprecated" statement was found and should be treated as an inference from omission, not a quoted deprecation notice.

## Q2. Trellis-CLI Commands — The Decisive Question

**Full command set** (from trellis-cli README, fetched via https://raw.githubusercontent.com/roots/trellis-cli/master/README.md):

| Command | Documented description (quoted) |
|---|---|
| `alias` | "Generate WP CLI aliases for remote environments" |
| `check` | "Checks if Trellis requirements are met" |
| `db` | "Commands for database management" |
| `deploy` | "Deploys a site to the specified environment" |
| `dotenv` | "Template .env files to local system" |
| `droplet` | "Commands for DigitalOcean Droplets" |
| `exec` | "Exec runs a command in the Trellis virtualenv" |
| `galaxy` | "Commands for Ansible Galaxy" |
| `info` | "Displays information about this Trellis project" |
| `init` | "Initializes an existing Trellis project" |
| `key` | "Commands for managing SSH keys" |
| `logs` | "Tails the Nginx log files" |
| `new` | "Creates a new Trellis project" |
| `open` | "Opens user-defined URLs (and more) which can act as shortcuts/bookmarks" |
| `provision` | "Provisions the specified environment" |
| `rollback` | "Rollsback the last deploy of the site on the specified environment" |
| `ssh` | "Connects to host via SSH" |
| `valet` | "Commands for Laravel Valet" |
| `vault` | "Commands for Ansible Vault" |
| `xdebug-tunnel` | "Commands for managing Xdebug tunnels" |
| `vm` | Lima VM management (per local-development docs, `trellis vm start`) — not in the raw README table captured above but documented at https://roots.io/trellis/docs/local-development/ |

**Is there a database push/pull command between environments in trellis-cli core? NO.** The only documented `db` subcommand is `trellis db open`, confirmed at https://roots.io/trellis/docs/database-access/ : usage shown as `trellis db open --app=sequel-pro production example.com` and `trellis db open --app=tableplus production example.com`. Per that fetch: "The documentation does not mention or document support for database push/pull synchronization between environments... The page focuses exclusively on opening database connections through GUI clients via SSH tunneling." `trellis db open` opens a local GUI DB client (Sequel Pro/Sequel Ace/TablePlus) connection over an SSH tunnel to view/edit the remote DB directly — it is not a data-transfer/sync command.

**Does trellis-cli or Trellis perform WordPress search-replace of domains during DB sync? NO — because there is no core DB sync feature to attach search-replace to.** This was explicitly requested and discussed but never merged, per two GitHub issues:
- https://github.com/roots/trellis-cli/issues/109 ("Feature request: Add `rsync` command") — proposed `trellis rsync push [--sync] <env> <site> <source> <destination>` / `pull` variants; a commenter wrote: "I wonder if Trellis-cli can also handle migrating databases and running search-replace, but that's obviously out of the scope of this discussion." Status: feature request, not shipped in core as of this research.
- https://github.com/roots/trellis/issues/654 ("Syncing db and uploads between envs (proposed enhancement)") — a contributor described a hypothetical design: "The db sync works using a combination of wp-cli (to export/import and search/replace) and rsync. The uploads sync simply uses rsync to copy the files." This is a proposal/discussion in the base Trellis repo, not a shipped feature.

**Is there an uploads/media sync command? NO**, not in trellis-cli core or Trellis core. Confirmed via search across roots.io/trellis/docs/cli/ and the trellis-cli GitHub repo — no `trellis uploads` or equivalent subcommand is documented. Community discussion thread: https://discourse.roots.io/t/pushing-or-pulling-uploads-with-trellis/4177.

**Where these capabilities actually live instead (community, not core):**
- `trellis-simple-sync` (https://github.com/creame/trellis-simple-sync) — third-party Ansible-based tool, `./bin/sync.sh <environment> <site> <type> <mode>` with push/pull modes for db and uploads, including GUID search-replace handling ("Skipping the GUID column is the default behaviour... you can prevent skipping GUIDs with `--extra-vars 'skip_guids=false'`").
- `trellis-database-uploads-migration` (https://github.com/valentinocossar/trellis-database-uploads-migration) — third-party Ansible playbook supporting `url_from`/`url_to` search-replace during db/uploads migration.
- `trellis-db-push-and-pull` (https://github.com/hamedb89/trellis-db-push-and-pull) — third-party, cited as the original inspiration for the above.
- `trellis-sync` (https://github.com/jasperf/trellis-sync) — third-party shell scripts, `sync-db-from-prod.sh` and `sync-all.sh`, plus example WP-CLI aliases.
- Roots Discourse thread https://discourse.roots.io/t/database-syncing/7283 discusses manual/scripted approaches using wp-cli.
- Roots Discourse thread https://discourse.roots.io/t/pushing-or-pulling-uploads-with-trellis/4177 shares a custom Ansible playbook invoked as `ansible-playbook uploads.yml -i hosts/staging --extra-vars="site=example.com mode=pull"`.

None of these third-party tools are part of trellis-cli's official README command table or roots.io/trellis/docs — all are community-maintained, separate repos.

**Fact statement on redundancy of the user's homegrown scripts:** Based on the above, `deploy/db-sync.sh` and `deploy/uploads-sync.sh` (rsync+SSH+WP search-replace) perform functionality that trellis-cli does NOT provide in core — trellis-cli's `db` command only opens a GUI DB client tunnel (`db open`), and there is no `uploads` sync command at all in either trellis-cli or Trellis core. Therefore, based strictly on what was found, trellis-cli would NOT make `deploy/db-sync.sh` or `deploy/uploads-sync.sh` redundant; the user would need to keep those scripts (or adopt a third-party community tool) alongside trellis-cli. `deploy/deploy.sh` (rsync+SSH code deploy) overlaps conceptually with trellis-cli's `deploy` command ("Deploys a site to the specified environment"), which is a documented core capability — that one script's function IS covered by trellis-cli core, per https://github.com/roots/trellis-cli README.

## Q3. Multisite

**Documented support: YES, path-based (subdirectory) multisite is officially documented.** Source: https://roots.io/trellis/docs/multisite/ . The `multisite.subdomains` setting in `group_vars/<environment>/wordpress_sites.yml` controls the mode; quoted: "Set to true if you're using a subdomain multisite install" — implying the default/false value is path-based (subdirectory) multisite, which is the mode used for the site subject of this research. Config is set per-environment (`multisite.enabled`, `multisite.subdomains`) per the same doc page.

**Documented caveat (verbatim, from official docs):** "This is a problem in WP core which occurs when WordPress is located in a subdirectory, as is the case with Bedrock." — referring to network main-site URLs containing `/wp/` before post/page pathnames when WP core is in a subdirectory (Bedrock's structure). The docs reference Bedrock issue #250 and recommend the "Multisite Fixes" plugin collection (a site-URL-fix plugin) as the resolution. This is a documented, named caveat with an official recommended fix — not merely a community workaround, since it's stated directly in Trellis's own multisite docs page.

**Documented caveat — SSL for subdomain multisite (not directly relevant to path-based, included for completeness):** "If you use Let's Encrypt as your SSL provider and your multisite install uses subdomains, currently you have to generate individual certificates for each of your subdomains" (https://roots.io/trellis/docs/multisite/). This applies to subdomain mode, not path-based.

**Community workarounds/issues found (GitHub, NOT part of official docs — separate from the documented caveat above):**
- https://github.com/roots/trellis/issues/266 — user reports subdomain-vs-subdirectory config confusion; notes `SUBDOMAIN_INSTALL` wp-config constant must align with the chosen mode or DB errors occur during multisite setup. Community issue thread, not a doc-sanctioned caveat.
- https://github.com/roots/trellis/issues/554 — "Multisite is-installed deploy check still failing": a user's deploy failed at the "WordPress Installed?" Ansible task with a DB error because `wp_blogs` table didn't exist yet for a domain+path query; workaround was manual WP-CLI multisite install via SSH. Community-reported issue/workaround, not documented as expected behavior.
- https://github.com/roots/trellis/issues/806 — nginx config generation issue for multisite when main domain uses a "www." prefix, producing an incorrect wildcard `server_name` entry. Community-reported bug/issue, not a documented limitation.
- https://github.com/roots/trellis/pull/283 — historical PR "Fix multisite install" referencing issue #266, discussing a "Constant MULTISITE already defined" notice in wp-config.php; discussion suggests some issues may stem from a WP core bug related to subdirectory installs (XML-RPC breaking). Historical PR/issue discussion, not current official doc content.

No dedicated multisite thread was found on Discourse in this search pass beyond the GitHub issues above; the official docs page (https://roots.io/trellis/docs/multisite/) remains the primary and most current source for documented behavior.

## Q4. Preview / Ephemeral Environments — Also Decisive

**NEGATIVE FINDING: No evidence of built-in ephemeral/per-branch/per-PR preview environment support in Trellis or trellis-cli.** Trellis's environment model is fixed: `development` / `staging` (optional) / `production` (per Q1, https://roots.io/trellis/docs/ansible/). No mechanism to dynamically create/destroy full environments on demand (à la Vercel previews) was found in official docs, the trellis-cli README, GitHub issues on either repo, or Roots Discourse.

**Searches performed and pages checked (for reproducibility):**
- WebSearch: `trellis-cli ephemeral preview environment per-branch per-PR` — no results tied to roots/trellis-cli; only unrelated tools (Preevy, generic preview-environment platforms) and an unrelated project also named "Trellis" (Trellis-Deployment/trellis, a serverless deployment pipeline, NOT roots/trellis).
- WebSearch: `roots/trellis "preview environment" OR "ephemeral environment" github` — no results referencing the Roots Trellis WordPress project; only generic ephemeral-environment tooling (Uffizzi, tilt-dev/ephemerator, GitHub Topics pages).
- WebSearch: `discourse.roots.io preview environment branch staging per pull request` — found two relevant but non-matching threads: https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651 (a user asking how to manually deploy different branches to different staging sites — described as a workaround discussion, not an existing feature) and https://discourse.roots.io/t/trouble-setting-up-staging-to-use-staging-branch/7660 (a user manually trying to point a staging environment at a non-master branch, hitting problems). Neither describes an automated/ephemeral on-demand system; both are manual multi-staging-environment configuration discussions.
- WebSearch: `site:github.com roots/trellis-cli issues preview environment` — no issue found specifically proposing or implementing preview/ephemeral environments in trellis-cli.
- WebSearch: `site:github.com roots/trellis issues "per branch" OR "per PR" environment` — no issue found matching; explicit conclusion drawn: "Trellis's environment model appears to be based on fixed environments like development, staging, and production rather than dynamic per-branch/per-PR environments."

**Conclusion:** Based on the searches above, ephemeral/on-demand per-branch or per-PR environments are NOT a documented or discovered feature of Trellis or trellis-cli, core or community. The closest related community pattern found is manually configuring additional named `staging`-like environments (e.g. `staging1`, `staging2`) mapped to different branches — a static, manually-provisioned multi-environment setup, not an automated ephemeral/on-demand system, per the Discourse thread https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651.

## Q5. Trellis + DDEV

**No official "Trellis + DDEV" integration doc exists.** Roots does publish an official DDEV guide, but it is scoped to Bedrock alone, not Trellis: https://roots.io/bedrock/docs/bedrock-with-ddev/ . Confirmed via WebFetch: the page's intro states "DDEV is a local PHP development environment. In this guide you will learn how to setup a Bedrock-based WordPress site with DDEV." Trellis is mentioned on that page only in navigation/footer boilerplate ("Powered by Radicle, Trellis, and DigitalOcean"; a sidebar link to the Trellis product) — not as part of the DDEV setup instructions. This is an official doc for Bedrock+DDEV, explicitly NOT for Trellis+DDEV.

**Combining Trellis with DDEV is a community pattern, not documented by Roots.** Trellis's own local-development docs (https://roots.io/trellis/docs/local-development/, fetched) do not mention DDEV, Docker, or Lando at all — verified via WebFetch: "The document does not contain any verbatim mentions of DDEV, Docker, Lando, or other alternative local development tools." The only documented alternative to Lima on that page is Laravel Valet, with the caution quoted in Q1 about dev/prod parity: "However, be warned that doesn't guarantee [development and production parity]... Using Valet locally means you aren't using Trellis _at all_ in development." By the same logic stated on that page, using DDEV instead of Trellis's Lima VM for local dev would mean not using Trellis at all in development — this is an inference applying the documented Valet caveat to DDEV by analogy, not a direct quote about DDEV specifically.

**No documented conflicts found in official sources.** No official Trellis or trellis-cli doc, and no roots/trellis or roots/trellis-cli GitHub issue, was found describing port conflicts or Vagrant/Docker conflicts between Trellis and DDEV specifically (search: `"trellis" "ddev" port conflict wordpress bedrock`, and `discourse.roots.io DDEV trellis` — no matching Discourse thread found). One general community data point surfaced in search results (unverified, not a primary source, paraphrased from search snippet, no direct primary URL confirmed in this pass): a developer reportedly described difficulty running Trellis's Vagrant-based local VM on WSL (filesystem/performance issues, needing to SSH into the VM and edit the Windows hosts file) as their reason for switching to DDEV — this describes friction with Trellis's old Vagrant workflow specifically, not a documented Trellis+DDEV port conflict, and since Q1 found Vagrant is no longer the current documented local-dev path (Lima is), this data point is of limited current relevance.

**UNVERIFIED:** Whether current Trellis (Lima-based `trellis vm start`) and DDEV produce a documented port conflict (e.g. both binding 80/443) when run simultaneously. Searches tried: `Trellis DDEV local development combine`, `discourse.roots.io DDEV trellis`, `"trellis" "ddev" port conflict wordpress bedrock`. No primary source (roots.io docs, GitHub issues, or Discourse thread) was found confirming or describing this conflict for the current Lima-based Trellis local dev tooling. This should be treated as an open gap, not a confirmed fact in either direction.

## Sources

Official Roots/Trellis docs (fetched):
- https://roots.io/trellis/docs/local-development/
- https://roots.io/trellis/docs/ansible/
- https://roots.io/trellis/docs/remote-server-setup/ (via WebFetch, referenced in Q1 fetch)
- https://roots.io/trellis/docs/installation/
- https://roots.io/trellis/docs/database-access/
- https://roots.io/trellis/docs/multisite/
- https://roots.io/bedrock/docs/bedrock-with-ddev/
- https://raw.githubusercontent.com/roots/trellis-cli/master/README.md

GitHub — roots/trellis-cli:
- https://github.com/roots/trellis-cli
- https://github.com/roots/trellis-cli/issues/109
- https://github.com/roots/trellis-cli/issues/53
- https://github.com/roots/trellis-cli/issues/5
- https://github.com/roots/trellis-cli/issues/76
- https://github.com/roots/trellis-cli/issues (list view)

GitHub — roots/trellis:
- https://github.com/roots/trellis/issues/654
- https://github.com/roots/trellis/issues/266
- https://github.com/roots/trellis/issues/554
- https://github.com/roots/trellis/issues/806
- https://github.com/roots/trellis/issues/438
- https://github.com/roots/trellis/pull/283
- https://github.com/roots/trellis/issues/595
- https://github.com/roots/trellis/pull/313
- https://github.com/roots/trellis/issues (list view)
- https://github.com/roots/trellis/blob/master/group_vars/all/vault.yml
- https://github.com/roots/trellis/blob/master/group_vars/all/main.yml

Roots Discourse:
- https://discourse.roots.io/t/database-syncing/7283
- https://discourse.roots.io/t/pushing-or-pulling-uploads-with-trellis/4177
- https://discourse.roots.io/t/deploy-to-multiple-staging-environments/8651
- https://discourse.roots.io/t/trouble-setting-up-staging-to-use-staging-branch/7660
- https://discourse.roots.io/c/trellis/12 (category index, checked for relevant threads)

Third-party/community tools referenced (NOT primary/official sources — cited only to answer "where does this live instead"):
- https://github.com/creame/trellis-simple-sync
- https://github.com/valentinocossar/trellis-database-uploads-migration
- https://github.com/hamedb89/trellis-db-push-and-pull
- https://github.com/jasperf/trellis-sync

Search queries used (WebSearch, no primary-source page found / included for reproducibility of negative findings):
- `site:roots.io/trellis/docs environments group_vars`
- `Trellis docs "group_vars" "all" environment inheritance`
- `trellis-cli "db" command database sync push pull search-replace`
- `trellis-cli "trellis db open" command github`
- `trellis-cli uploads sync command roots.io docs`
- `roots/trellis multisite subdirectory path-based github issue`
- `trellis-cli ephemeral preview environment per-branch per-PR`
- `roots/trellis "preview environment" OR "ephemeral environment" github`
- `discourse.roots.io preview environment branch staging per pull request`
- `site:github.com roots/trellis-cli issues preview environment`
- `site:github.com roots/trellis issues "per branch" OR "per PR" environment`
- `Trellis DDEV local development combine`
- `discourse.roots.io DDEV trellis`
- `"trellis" "ddev" port conflict wordpress bedrock`
