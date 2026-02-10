<?php

declare(strict_types=1);

/**
 * GlobalInfrastructureManager Unit Tests
 *
 * Tests the Traefik reverse proxy manager — the shared infrastructure
 * that connects all tuti-cli projects together via a Docker network.
 *
 * Installed at: ~/.tuti/infrastructure/traefik/
 *
 * @see \App\Services\Infrastructure\GlobalInfrastructureManager
 */

use App\Services\Infrastructure\GlobalInfrastructureManager;
use Illuminate\Support\Facades\Process;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->globalTutiPath = createTestDirectory();
    $this->service = new GlobalInfrastructureManager($this->globalTutiPath);
});

afterEach(function (): void {
    cleanupTestDirectory($this->globalTutiPath);
});

// ─── Helpers ────────────────────────────────────────────────────────────

/**
 * Normalize process command to a plain string (strip shell escaping quotes).
 */
function infraCommandStr(object $process): string
{
    $cmd = $process->command;

    return is_array($cmd) ? implode(' ', $cmd) : str_replace("'", '', $cmd);
}

function installTraefik(string $globalPath): void
{
    $traefikPath = $globalPath . '/infrastructure/traefik';
    mkdir($traefikPath, 0755, true);
    file_put_contents($traefikPath . '/docker-compose.yml', 'version: "3.8"');
}

function fakeDockerRunning(): void
{
    Process::fake([
        '*docker*info*' => Process::result('Docker running'),
        '*docker*compose*' => Process::result('ok'),
        '*docker*network*' => Process::result('ok'),
        '*mkcert*' => Process::result(errorOutput: 'not found', exitCode: 1),
        '*htpasswd*' => Process::result(errorOutput: 'not found', exitCode: 1),
        '*openssl*' => Process::result('ok'),
    ]);
}

// ─── Path resolution ────────────────────────────────────────────────────

describe('path resolution', function (): void {

    it('returns infrastructure path under global tuti path', function (): void {
        expect($this->service->getInfrastructurePath())
            ->toBe($this->globalTutiPath . DIRECTORY_SEPARATOR . 'infrastructure');
    });

    it('returns the Traefik dashboard URL', function (): void {
        expect($this->service->getDashboardUrl())
            ->toBe('https://traefik.local.test');
    });
});

// ─── isInstalled() ──────────────────────────────────────────────────────

describe('isInstalled', function (): void {

    it('returns false when traefik directory does not exist', function (): void {
        expect($this->service->isInstalled())->toBeFalse();
    });

    it('returns false when directory exists but no docker-compose.yml', function (): void {
        mkdir($this->globalTutiPath . '/infrastructure/traefik', 0755, true);

        expect($this->service->isInstalled())->toBeFalse();
    });

    it('returns true when docker-compose.yml exists in traefik dir', function (): void {
        installTraefik($this->globalTutiPath);

        expect($this->service->isInstalled())->toBeTrue();
    });
});

// ─── isRunning() ────────────────────────────────────────────────────────

describe('isRunning', function (): void {

    it('returns false when not installed', function (): void {
        expect($this->service->isRunning())->toBeFalse();
    });

    it('returns false when docker compose ps returns empty', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*compose*' => Process::result(output: ''),
        ]);

        expect($this->service->isRunning())->toBeFalse();
    });

    it('returns true when docker compose ps reports running containers', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Service":"traefik","State":"running"}',
            ),
        ]);

        expect($this->service->isRunning())->toBeTrue();
    });

    it('returns false when containers exist but are not running', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Service":"traefik","State":"exited"}',
            ),
        ]);

        expect($this->service->isRunning())->toBeFalse();
    });
});

// ─── start() ────────────────────────────────────────────────────────────

describe('start', function (): void {

    it('throws when not installed', function (): void {
        fakeDockerRunning();

        expect(fn () => $this->service->start())
            ->toThrow(RuntimeException::class, 'not installed');
    });

    it('runs docker compose up when installed', function (): void {
        installTraefik($this->globalTutiPath);
        fakeDockerRunning();

        $this->service->start();

        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'docker compose')
            && str_contains(infraCommandStr($process), 'up -d'));
    });

    it('ensures network exists before starting', function (): void {
        installTraefik($this->globalTutiPath);
        fakeDockerRunning();

        $this->service->start();

        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'docker network'));
    });

    it('throws when docker compose up fails', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*network*' => Process::result('ok'),
            '*docker*compose*' => Process::result(errorOutput: 'port in use', exitCode: 1),
        ]);

        expect(fn () => $this->service->start())
            ->toThrow(RuntimeException::class, 'Failed to start');
    });
});

// ─── stop() ─────────────────────────────────────────────────────────────

describe('stop', function (): void {

    it('is a no-op when not installed', function (): void {
        Process::fake();

        $this->service->stop();

        // Should not have run any Docker commands
        Process::assertNotRan('*docker*compose*');
    });

    it('runs docker compose down when installed', function (): void {
        installTraefik($this->globalTutiPath);
        fakeDockerRunning();

        $this->service->stop();

        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'docker compose')
            && str_contains(infraCommandStr($process), 'down'));
    });
});

// ─── restart() ──────────────────────────────────────────────────────────

describe('restart', function (): void {

    it('calls stop then start', function (): void {
        installTraefik($this->globalTutiPath);
        fakeDockerRunning();

        $this->service->restart();

        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'down'));
        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'up -d'));
    });
});

// ─── ensureNetworkExists() ──────────────────────────────────────────────

describe('ensureNetworkExists', function (): void {

    it('returns true when network already exists', function (): void {
        Process::fake([
            '*docker*network*inspect*' => Process::result('ok'),
        ]);

        expect($this->service->ensureNetworkExists())->toBeTrue();
    });

    it('creates network when it does not exist', function (): void {
        Process::fake([
            '*docker*network*inspect*' => Process::result(exitCode: 1),
            '*docker*network*create*' => Process::result('created'),
        ]);

        expect($this->service->ensureNetworkExists())->toBeTrue();

        Process::assertRan(fn ($process) => str_contains(infraCommandStr($process), 'docker network create'));
    });

    it('throws when network creation fails', function (): void {
        Process::fake([
            '*docker*network*inspect*' => Process::result(exitCode: 1),
            '*docker*network*create*' => Process::result(errorOutput: 'permission denied', exitCode: 1),
        ]);

        expect(fn () => $this->service->ensureNetworkExists())
            ->toThrow(RuntimeException::class, 'Failed to create Docker network');
    });
});

// ─── getStatus() ────────────────────────────────────────────────────────

describe('getStatus', function (): void {

    it('reports not_installed when traefik is not set up', function (): void {
        Process::fake([
            '*docker*network*' => Process::result(exitCode: 1),
        ]);

        $status = $this->service->getStatus();

        expect($status['traefik']['installed'])->toBeFalse();
        expect($status['traefik']['health'])->toBe('not_installed');
    });

    it('reports stopped when installed but not running', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*compose*' => Process::result(output: ''),
            '*docker*network*' => Process::result(exitCode: 1),
        ]);

        $status = $this->service->getStatus();

        expect($status['traefik']['installed'])->toBeTrue();
        expect($status['traefik']['running'])->toBeFalse();
        expect($status['traefik']['health'])->toBe('stopped');
    });

    it('reports healthy when installed and running', function (): void {
        installTraefik($this->globalTutiPath);
        Process::fake([
            '*docker*compose*' => Process::result(
                output: '{"Service":"traefik","State":"running"}',
            ),
            '*docker*network*' => Process::result('ok'),
        ]);

        $status = $this->service->getStatus();

        expect($status['traefik']['installed'])->toBeTrue();
        expect($status['traefik']['running'])->toBeTrue();
        expect($status['traefik']['health'])->toBe('healthy');
    });

    it('reports network status', function (): void {
        Process::fake([
            '*docker*network*inspect*' => Process::result('ok'),
            '*docker*compose*' => Process::result(output: ''),
        ]);

        $status = $this->service->getStatus();

        expect($status['network']['installed'])->toBeTrue();
        expect($status['network']['health'])->toBe('healthy');
    });
});
