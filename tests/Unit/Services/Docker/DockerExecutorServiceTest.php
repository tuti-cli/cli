<?php

declare(strict_types=1);

/**
 * DockerExecutorService Unit Tests
 *
 * Tests the service that runs commands inside Docker containers
 * (composer, artisan, npm, wp-cli) without requiring them on the host.
 *
 * @see DockerExecutorService
 */

use App\Contracts\DockerExecutionResult;
use App\Services\Docker\DockerExecutorService;
use Illuminate\Support\Facades\Process;

// ─── Setup ──────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->tempDir = createTestDirectory();
    $this->service = new DockerExecutorService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->tempDir);
});

// ─── Helper: fake Docker as available ───────────────────────────────────

/**
 * Normalize process command to a plain string (strip shell escaping quotes).
 */
function execCommandStr(object $process): string
{
    $cmd = $process->command;

    return is_array($cmd) ? implode(' ', $cmd) : str_replace("'", '', $cmd);
}

function fakeDockerAvailable(array $extraFakes = []): void
{
    Process::fake(array_merge([
        '*docker*info*' => Process::result('Docker is running'),
        '*docker*run*' => Process::result('command output'),
        '*docker*pull*' => Process::result('pulled'),
        '*docker*image*inspect*' => Process::result('exists'),
        '*docker*exec*' => Process::result('exec output'),
    ], $extraFakes));
}

function fakeDockerUnavailable(): void
{
    Process::fake([
        '*docker*info*' => Process::result(errorOutput: 'Cannot connect', exitCode: 1),
    ]);
}

// ─── isDockerAvailable() ────────────────────────────────────────────────

describe('isDockerAvailable', function (): void {

    it('returns true when docker info succeeds', function (): void {
        fakeDockerAvailable();

        expect($this->service->isDockerAvailable())->toBeTrue();
    });

    it('returns false when docker info fails', function (): void {
        fakeDockerUnavailable();

        expect($this->service->isDockerAvailable())->toBeFalse();
    });
});

// ─── getPhpImage() ──────────────────────────────────────────────────────
// Pure string method — no Docker needed at all.

describe('getPhpImage', function (): void {

    it('returns default image with version', function (): void {
        expect($this->service->getPhpImage())->toBe('serversideup/php:8.4-cli');
    });

    it('accepts a custom version', function (): void {
        expect($this->service->getPhpImage('8.3'))->toBe('serversideup/php:8.3-cli');
    });

    it('uses custom image from constructor', function (): void {
        $service = new DockerExecutorService(phpImage: 'custom/php');

        expect($service->getPhpImage('8.4'))->toBe('custom/php:8.4-cli');
    });
});

// ─── runComposer() ──────────────────────────────────────────────────────

describe('runComposer', function (): void {

    it('returns a DockerExecutionResult', function (): void {
        fakeDockerAvailable();

        $result = $this->service->runComposer('install', $this->tempDir);

        expect($result)->toBeInstanceOf(DockerExecutionResult::class);
        expect($result->successful)->toBeTrue();
    });

    it('runs the composer command via docker', function (): void {
        fakeDockerAvailable();

        $this->service->runComposer('install --no-dev', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'composer install --no-dev'));
    });

    it('throws when Docker is unavailable', function (): void {
        fakeDockerUnavailable();

        expect(fn () => $this->service->runComposer('install', $this->tempDir))
            ->toThrow(RuntimeException::class, 'Docker is not available');
    });

    it('creates directory if it does not exist', function (): void {
        fakeDockerAvailable();
        $newDir = $this->tempDir . '/new-project';

        $this->service->runComposer('install', $newDir);

        expect($newDir)->toBeDirectory();
    });
});

// ─── runArtisan() ───────────────────────────────────────────────────────

describe('runArtisan', function (): void {

    it('runs artisan command via docker', function (): void {
        fakeDockerAvailable();
        // Create a fake artisan file so the check passes
        file_put_contents($this->tempDir . '/artisan', '<?php');

        $this->service->runArtisan('migrate', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'php artisan migrate'));
    });

    it('returns failure when no artisan file exists', function (): void {
        fakeDockerAvailable();

        $result = $this->service->runArtisan('migrate', $this->tempDir);

        expect($result->successful)->toBeFalse();
        expect($result->errorOutput)->toContain('No Laravel project found');
    });

    it('sets DISABLE_DEFAULT_CONFIG env var', function (): void {
        fakeDockerAvailable();
        file_put_contents($this->tempDir . '/artisan', '<?php');

        $this->service->runArtisan('key:generate', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'DISABLE_DEFAULT_CONFIG=true'));
    });
});

// ─── runNpm() ───────────────────────────────────────────────────────────

describe('runNpm', function (): void {

    it('runs npm command via docker with node image', function (): void {
        fakeDockerAvailable();

        $this->service->runNpm('install', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'npm install')
            && str_contains(execCommandStr($process), 'node:20-alpine'));
    });

    it('returns a DockerExecutionResult', function (): void {
        fakeDockerAvailable();

        $result = $this->service->runNpm('build', $this->tempDir);

        expect($result)->toBeInstanceOf(DockerExecutionResult::class);
    });
});

// ─── runWpCli() ─────────────────────────────────────────────────────────

describe('runWpCli', function (): void {

    it('runs wp-cli via docker run with wordpress cli image', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'download'], $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'wordpress:cli-2-php8.3')
            && str_contains(execCommandStr($process), '/usr/local/bin/wp')
            && str_contains(execCommandStr($process), 'core')
            && str_contains(execCommandStr($process), 'download'));
    });

    it('mounts working directory to /var/www/html', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['plugin', 'list'], $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), $this->tempDir . ':/var/www/html'));
    });

    it('connects to network when specified', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'install'], $this->tempDir, [], 'myproject_dev_network');

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), '--network')
            && str_contains(execCommandStr($process), 'myproject_dev_network'));
    });

    it('does not include network flag when not specified', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'download'], $this->tempDir);

        Process::assertRan(fn (object $process): bool => ! str_contains(execCommandStr($process), '--network'));
    });

    it('sets default wordpress database environment variables', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'install'], $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'WORDPRESS_DB_HOST=database')
            && str_contains(execCommandStr($process), 'WORDPRESS_DB_NAME=wordpress'));
    });

    it('uses php with increased memory limit', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'download'], $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'php')
            && str_contains(execCommandStr($process), '-d')
            && str_contains(execCommandStr($process), 'memory_limit=512M'));
    });

    it('passes arguments as separate array elements (no shell interpolation)', function (): void {
        fakeDockerAvailable();

        $this->service->runWpCli(['core', 'download', '--version=6.4', '--locale=en_US'], $this->tempDir);

        $cmd = '';
        Process::assertRan(function (object $process) use (&$cmd): bool {
            $cmd = execCommandStr($process);

            return true;
        });

        // Arguments should be separate, not interpolated into a shell string
        expect($cmd)->toContain('core')
            ->and($cmd)->toContain('download')
            ->and($cmd)->toContain('--version=6.4')
            ->and($cmd)->toContain('--locale=en_US');
    });
});

// ─── exec() ─────────────────────────────────────────────────────────────

describe('exec', function (): void {

    it('builds a docker run command with the right image', function (): void {
        fakeDockerAvailable();

        $this->service->exec('alpine:latest', 'echo hello', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'alpine:latest')
            && str_contains(execCommandStr($process), 'echo hello'));
    });

    it('mounts the working directory as /app', function (): void {
        fakeDockerAvailable();

        $this->service->exec('alpine', 'ls', $this->tempDir);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), $this->tempDir . ':/app'));
    });

    it('passes environment variables', function (): void {
        fakeDockerAvailable();

        $this->service->exec('alpine', 'env', $this->tempDir, ['FOO' => 'bar']);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'FOO=bar'));
    });

    it('returns success result on successful execution', function (): void {
        fakeDockerAvailable([
            '*docker*run*' => Process::result(output: 'hello world'),
        ]);

        $result = $this->service->exec('alpine', 'echo hello', $this->tempDir);

        expect($result->successful)->toBeTrue();
        expect($result->output)->toContain('hello world');
        expect($result->exitCode)->toBe(0);
    });

    it('returns failure result on failed execution', function (): void {
        fakeDockerAvailable([
            '*docker*run*' => Process::result(errorOutput: 'command not found', exitCode: 127),
        ]);

        $result = $this->service->exec('alpine', 'badcmd', $this->tempDir);

        expect($result->successful)->toBeFalse();
        expect($result->exitCode)->toBe(127);
    });
});

// ─── pullImage() ────────────────────────────────────────────────────────

describe('pullImage', function (): void {

    it('pulls the specified image', function (): void {
        fakeDockerAvailable();

        $result = $this->service->pullImage('nginx:latest');

        expect($result->successful)->toBeTrue();

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'docker pull nginx:latest'));
    });
});

// ─── imageExists() ──────────────────────────────────────────────────────

describe('imageExists', function (): void {

    it('returns true when image exists locally', function (): void {
        fakeDockerAvailable();

        expect($this->service->imageExists('alpine:latest'))->toBeTrue();
    });

    it('returns false when image does not exist', function (): void {
        Process::fake([
            '*docker*info*' => Process::result('ok'),
            '*docker*image*inspect*' => Process::result(errorOutput: 'not found', exitCode: 1),
        ]);

        expect($this->service->imageExists('nonexistent:image'))->toBeFalse();
    });
});

// ─── execInContainer() ──────────────────────────────────────────────────

describe('execInContainer', function (): void {

    it('executes a command in a running container', function (): void {
        fakeDockerAvailable();

        $result = $this->service->execInContainer('myapp_dev_app', 'php -v');

        expect($result->successful)->toBeTrue();

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'docker exec')
            && str_contains(execCommandStr($process), 'myapp_dev_app')
            && str_contains(execCommandStr($process), 'php -v'));
    });

    it('passes environment variables to the container', function (): void {
        fakeDockerAvailable();

        $this->service->execInContainer('myapp_dev_app', 'env', ['APP_ENV' => 'testing']);

        Process::assertRan(fn (object $process): bool => str_contains(execCommandStr($process), 'APP_ENV=testing'));
    });
});
