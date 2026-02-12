<?php

declare(strict_types=1);

/**
 * DockerService Unit Tests
 *
 * Tests the general-purpose Docker Compose wrapper that manages
 * a project's containers: start, stop, status, exec, logs, etc.
 *
 * @see DockerService
 */

use App\Services\Docker\DockerService;
use Illuminate\Support\Facades\Process;

// ─── Setup ──────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->service = new DockerService(
        composePath: '/projects/myapp/.tuti/docker-compose.yml',
        projectName: 'myapp',
    );
});

// ─── Helpers ────────────────────────────────────────────────────────────

/**
 * Normalize process command to a plain string (strip shell escaping quotes).
 * When Process::run() receives an array, Laravel escapes each argument.
 */
function commandStr(object $process): string
{
    $cmd = $process->command;

    return is_array($cmd) ? implode(' ', $cmd) : str_replace("'", '', $cmd);
}

function fakeComposeRunning(array $extras = []): void
{
    Process::fake(array_merge([
        '*docker*info*' => Process::result('ok'),
        '*docker*compose*' => Process::result('ok'),
        '*docker*inspect*' => Process::result('172.18.0.2'),
        '*lsof*' => Process::result(exitCode: 1),
    ], $extras));
}

// ─── isRunning() ────────────────────────────────────────────────────────

describe('isRunning', function (): void {

    it('returns true when docker info succeeds', function (): void {
        Process::fake(['*docker*info*' => Process::result('ok')]);

        expect($this->service->isRunning())->toBeTrue();
    });

    it('returns false when docker info fails', function (): void {
        Process::fake(['*docker*info*' => Process::result(exitCode: 1)]);

        expect($this->service->isRunning())->toBeFalse();
    });
});

// ─── start() / stop() / restart() ──────────────────────────────────────

describe('start', function (): void {

    it('runs docker compose up -d', function (): void {
        fakeComposeRunning();

        $result = $this->service->start();

        expect($result)->toBeTrue();
        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'docker compose')
            && str_contains(commandStr($p), 'up -d'));
    });

    it('includes compose file and project name in command', function (): void {
        fakeComposeRunning();

        $this->service->start();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), '-f /projects/myapp/.tuti/docker-compose.yml')
            && str_contains(commandStr($p), '-p myapp'));
    });

    it('returns false when docker compose fails', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(exitCode: 1),
        ]);

        expect($this->service->start())->toBeFalse();
    });
});

describe('stop', function (): void {

    it('runs docker compose down', function (): void {
        fakeComposeRunning();

        $result = $this->service->stop();

        expect($result)->toBeTrue();
        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'down'));
    });
});

describe('restart', function (): void {

    it('runs docker compose restart', function (): void {
        fakeComposeRunning();

        $this->service->restart();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'restart'));
    });

    it('restarts a specific service', function (): void {
        fakeComposeRunning();

        $this->service->restart('redis');

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'restart')
            && str_contains(commandStr($p), 'redis'));
    });
});

// ─── pullImages() ───────────────────────────────────────────────────────

describe('pullImages', function (): void {

    it('runs docker compose pull', function (): void {
        fakeComposeRunning();

        $result = $this->service->pullImages();

        expect($result)->toBeTrue();
        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'pull'));
    });
});

// ─── getStatus() ────────────────────────────────────────────────────────
// Tests NDJSON parsing — the format Docker Compose v2 uses.

describe('getStatus', function (): void {

    it('parses NDJSON output into structured service array', function (): void {
        $ndjson = '{"Service":"app","State":"running","Health":"healthy","Publishers":[{"PublishedPort":8080,"TargetPort":80}]}' . "\n"
            . '{"Service":"postgres","State":"running","Health":"healthy","Publishers":[]}';

        Process::fake([
            '*docker*compose*' => Process::result(output: $ndjson),
        ]);

        $status = $this->service->getStatus();

        expect($status)->toHaveCount(2);
        expect($status[0]['name'])->toBe('app');
        expect($status[0]['status'])->toBe('running');
        expect($status[0]['health'])->toBe('healthy');
        expect($status[0]['ports'])->toBe(['8080:80']);
        expect($status[1]['name'])->toBe('postgres');
    });

    it('returns empty array when command fails', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(exitCode: 1),
        ]);

        expect($this->service->getStatus())->toBeEmpty();
    });

    it('uses Name fallback when Service key is missing', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Name":"legacy-app","State":"running","Health":"unknown"}',
            ),
        ]);

        $status = $this->service->getStatus();

        expect($status[0]['name'])->toBe('legacy-app');
    });

    it('handles empty output', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(output: ''),
        ]);

        expect($this->service->getStatus())->toBeEmpty();
    });

    it('parses multiple port mappings', function (): void {
        $json = '{"Service":"app","State":"running","Health":"healthy","Publishers":[{"PublishedPort":8080,"TargetPort":80},{"PublishedPort":8443,"TargetPort":443}]}';

        Process::fake([
            '*docker*compose*' => Process::result(output: $json),
        ]);

        $status = $this->service->getStatus();

        expect($status[0]['ports'])->toBe(['8080:80', '8443:443']);
    });
});

// ─── exec() ─────────────────────────────────────────────────────────────

describe('exec', function (): void {

    it('executes command in a container', function (): void {
        fakeComposeRunning([
            '*docker*compose*' => Process::result(output: 'command output'),
        ]);

        $result = $this->service->exec('app', 'php artisan migrate');

        expect($result['success'])->toBeTrue();
        expect($result['output'])->toContain('command output');
    });

    it('uses -T flag by default (non-TTY)', function (): void {
        fakeComposeRunning();

        $this->service->exec('app', 'ls');

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'exec -T app sh -c ls'));
    });

    it('returns exit code and error output on failure', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(
                errorOutput: 'command failed',
                exitCode: 1,
            ),
        ]);

        $result = $this->service->exec('app', 'bad-command');

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('command failed');
        expect($result['exit_code'])->toBe(1);
    });
});

// ─── getContainerIp() ───────────────────────────────────────────────────

describe('getContainerIp', function (): void {

    it('returns IP address from docker inspect', function (): void {
        Process::fake([
            '*docker*inspect*' => Process::result(output: '172.18.0.5'),
        ]);

        expect($this->service->getContainerIp('myapp_dev_app'))->toBe('172.18.0.5');
    });

    it('returns null when container does not exist', function (): void {
        Process::fake([
            '*docker*inspect*' => Process::result(exitCode: 1),
        ]);

        expect($this->service->getContainerIp('nonexistent'))->toBeNull();
    });

    it('returns null when output is empty', function (): void {
        Process::fake([
            '*docker*inspect*' => Process::result(output: ''),
        ]);

        expect($this->service->getContainerIp('myapp_dev_app'))->toBeNull();
    });
});

// ─── isServiceHealthy() / getServiceStatus() ───────────────────────────

describe('isServiceHealthy', function (): void {

    it('returns true when service health is healthy', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Service":"app","State":"running","Health":"healthy","Publishers":[]}',
            ),
        ]);

        expect($this->service->isServiceHealthy('app'))->toBeTrue();
    });

    it('returns true when service is running (even without health)', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Service":"redis","State":"running","Health":"","Publishers":[]}',
            ),
        ]);

        expect($this->service->isServiceHealthy('redis'))->toBeTrue();
    });

    it('returns false when service is not found', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(output: ''),
        ]);

        expect($this->service->isServiceHealthy('nonexistent'))->toBeFalse();
    });
});

describe('getServiceStatus', function (): void {

    it('returns status of a specific service', function (): void {
        $ndjson = '{"Service":"app","State":"running","Health":"healthy","Publishers":[]}' . "\n"
            . '{"Service":"redis","State":"running","Health":"healthy","Publishers":[]}';

        Process::fake([
            '*docker*compose*' => Process::result(output: $ndjson),
        ]);

        $status = $this->service->getServiceStatus('redis');

        expect($status['name'])->toBe('redis');
        expect($status['status'])->toBe('running');
    });

    it('returns not_found for missing service', function (): void {
        Process::fake([
            '*docker*compose*' => Process::result(output: ''),
        ]);

        $status = $this->service->getServiceStatus('nonexistent');

        expect($status['status'])->toBe('not_found');
        expect($status['health'])->toBe('unknown');
    });
});

// ─── build() / destroy() ────────────────────────────────────────────────

describe('build', function (): void {

    it('runs docker compose build', function (): void {
        fakeComposeRunning();

        $this->service->build();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'build'));
    });

    it('builds a specific service', function (): void {
        fakeComposeRunning();

        $this->service->build('app');

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'build')
            && str_contains(commandStr($p), 'app'));
    });
});

describe('destroy', function (): void {

    it('runs docker compose down with -v flag', function (): void {
        fakeComposeRunning();

        $this->service->destroy();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), 'down -v'));
    });
});

// ─── env file handling ──────────────────────────────────────────────────

describe('env file', function (): void {

    it('includes env file in command when it exists', function (): void {
        $tempDir = createTestDirectory();
        $envFile = $tempDir . '/local.env';
        file_put_contents($envFile, 'APP_ENV=dev');

        $service = new DockerService(
            composePath: '/projects/myapp/.tuti/docker-compose.yml',
            projectName: 'myapp',
            envFilePath: $envFile,
        );

        fakeComposeRunning();

        $service->start();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), '--env-file')
            && str_contains(commandStr($p), $envFile));

        cleanupTestDirectory($tempDir);
    });

    it('omits env file when path is null', function (): void {
        fakeComposeRunning();

        $this->service->start();

        Process::assertRan(fn ($p) => ! str_contains(commandStr($p), '--env-file'));
    });
});

// ─── constructor injection ──────────────────────────────────────────────

describe('constructor', function (): void {

    it('can be instantiated with just composePath and projectName', function (): void {
        $service = new DockerService('/path/to/compose.yml', 'myproject');

        expect($service)->toBeInstanceOf(DockerService::class);
    });

    it('uses injected project name in commands', function (): void {
        $service = new DockerService('/any/path.yml', 'custom-project');
        fakeComposeRunning();

        $service->start();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), '-p custom-project'));
    });

    it('uses injected compose path in commands', function (): void {
        $service = new DockerService('/custom/docker-compose.yml', 'app');
        fakeComposeRunning();

        $service->start();

        Process::assertRan(fn ($p) => str_contains(commandStr($p), '-f /custom/docker-compose.yml'));
    });
});
