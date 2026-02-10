<?php

declare(strict_types=1);

/**
 * StackFilesCopierService Unit Tests
 *
 * Tests the file copier that moves stack template files into a project's
 * .tuti/ directory. This is the "installer" — when you run `tuti stack:laravel`,
 * this service copies Dockerfile, entrypoint scripts, compose files, etc.
 *
 * It also handles permissions (scripts get chmod 0755) and creates a
 * health.php file for Docker health checks in Laravel projects.
 *
 * We need TWO temp directories:
 *   1. "stackDir"   — simulates the stubs/stacks/laravel/ source
 *   2. "projectDir" — simulates the user's project with .tuti/ inside
 *
 * Dependency chain: StackFilesCopierService → ProjectDirectoryService
 *                   ProjectDirectoryService → WorkingDirectoryService
 *
 * @see \App\Services\Stack\StackFilesCopierService
 */

use App\Services\Context\WorkingDirectoryService;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Stack\StackFilesCopierService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// Two temp directories: one pretending to be the stack source (stubs),
// the other pretending to be the user's project.

beforeEach(function (): void {
    $this->stackDir = createTestDirectory();
    $this->projectDir = createTestDirectory();

    // Wire up the dependency chain pointing at the project dir
    $workingDir = new WorkingDirectoryService;
    $workingDir->setWorkingDirectory($this->projectDir);

    $this->directoryService = new ProjectDirectoryService($workingDir);

    // Create .tuti in the project (the copier writes here)
    mkdir($this->projectDir . '/.tuti', 0755, true);

    $this->service = new StackFilesCopierService($this->directoryService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->stackDir);
    cleanupTestDirectory($this->projectDir);
});

// ─── Helpers: build a fake stack source directory ────────────────────────

function createStackStructure(string $stackDir, array $dirs = [], array $files = []): void
{
    foreach ($dirs as $dir) {
        mkdir($stackDir . '/' . $dir, 0755, true);
    }

    foreach ($files as $path => $content) {
        $fullPath = $stackDir . '/' . $path;
        $parentDir = dirname($fullPath);

        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        file_put_contents($fullPath, $content);
    }
}

// ─── copyFromStack() ────────────────────────────────────────────────────
// The main method: copies directories + files, sets permissions, creates
// health check. This is the full "install stack into project" flow.

describe('copyFromStack', function (): void {

    it('copies docker directory to .tuti/docker', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker/Dockerfile' => 'FROM php:8.4-fpm',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/docker/Dockerfile')
            ->toBeFile()
            ->and(file_get_contents($this->projectDir . '/.tuti/docker/Dockerfile'))
            ->toBe('FROM php:8.4-fpm');
    });

    it('copies environments directory to .tuti/environments', function (): void {
        createStackStructure($this->stackDir, [], [
            'environments/.env.dev.example' => 'APP_ENV=dev',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/environments/.env.dev.example')
            ->toBeFile()
            ->and(file_get_contents($this->projectDir . '/.tuti/environments/.env.dev.example'))
            ->toBe('APP_ENV=dev');
    });

    it('copies scripts directory to .tuti/scripts', function (): void {
        createStackStructure($this->stackDir, [], [
            'scripts/entrypoint-dev.sh' => '#!/bin/bash',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/scripts/entrypoint-dev.sh')
            ->toBeFile();
    });

    it('copies individual root files to .tuti', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker-compose.yml' => 'version: "3.8"',
            'docker-compose.dev.yml' => 'version: "3.8"',
            'stack.json' => '{"name": "laravel"}',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/docker-compose.yml')->toBeFile();
        expect($this->projectDir . '/.tuti/docker-compose.dev.yml')->toBeFile();
        expect($this->projectDir . '/.tuti/stack.json')->toBeFile();
    });

    it('only copies files that exist in the source', function (): void {
        // Only docker-compose.yml — no dev/staging/prod overlays
        createStackStructure($this->stackDir, [], [
            'docker-compose.yml' => 'version: "3.8"',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/docker-compose.yml')->toBeFile();
        expect(file_exists($this->projectDir . '/.tuti/docker-compose.dev.yml'))->toBeFalse();
        expect(file_exists($this->projectDir . '/.tuti/docker-compose.staging.yml'))->toBeFalse();
    });

    it('makes shell scripts executable (0755)', function (): void {
        createStackStructure($this->stackDir, [], [
            'scripts/entrypoint-dev.sh' => '#!/bin/bash',
            'scripts/setup.sh' => '#!/bin/bash',
        ]);

        $this->service->copyFromStack($this->stackDir);

        $perms1 = fileperms($this->projectDir . '/.tuti/scripts/entrypoint-dev.sh') & 0777;
        $perms2 = fileperms($this->projectDir . '/.tuti/scripts/setup.sh') & 0777;

        expect($perms1)->toBe(0755);
        expect($perms2)->toBe(0755);
    });

    it('makes root .sh files executable too', function (): void {
        createStackStructure($this->stackDir, [], [
            'deploy.sh' => '#!/bin/bash',
        ]);

        $this->service->copyFromStack($this->stackDir);

        $perms = fileperms($this->projectDir . '/.tuti/deploy.sh') & 0777;

        expect($perms)->toBe(0755);
    });

    it('copies nested subdirectories recursively', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker/Dockerfile' => 'FROM php:8.4',
            'docker/conf/nginx.conf' => 'server { }',
            'docker/conf/php/php.ini' => 'memory_limit=256M',
        ]);

        $this->service->copyFromStack($this->stackDir);

        expect($this->projectDir . '/.tuti/docker/Dockerfile')->toBeFile();
        expect($this->projectDir . '/.tuti/docker/conf/nginx.conf')->toBeFile();
        expect($this->projectDir . '/.tuti/docker/conf/php/php.ini')->toBeFile();

        expect(file_get_contents($this->projectDir . '/.tuti/docker/conf/php/php.ini'))
            ->toBe('memory_limit=256M');
    });

    it('returns true on success', function (): void {
        createStackStructure($this->stackDir, ['docker'], []);

        $result = $this->service->copyFromStack($this->stackDir);

        expect($result)->toBeTrue();
    });

    it('throws RuntimeException when stack path does not exist', function (): void {
        expect(fn () => $this->service->copyFromStack('/nonexistent/stack/path'))
            ->toThrow(RuntimeException::class, 'Stack directory not found');
    });
});

// ─── createHealthCheckFile() ────────────────────────────────────────────
// Creates public/health.php for Docker health checks. This small PHP file
// returns {"status":"ok"} without booting Laravel — much faster for Docker
// HEALTHCHECK polling every few seconds.

describe('createHealthCheckFile', function (): void {

    it('creates health.php when public directory exists', function (): void {
        mkdir($this->projectDir . '/public', 0755);

        $this->service->createHealthCheckFile();

        expect($this->projectDir . '/public/health.php')->toBeFile();
    });

    it('writes valid PHP that returns JSON status', function (): void {
        mkdir($this->projectDir . '/public', 0755);

        $this->service->createHealthCheckFile();

        $content = file_get_contents($this->projectDir . '/public/health.php');

        expect($content)
            ->toContain('<?php')
            ->toContain("'status' => 'ok'")
            ->toContain('http_response_code(200)')
            ->toContain('application/json');
    });

    it('skips when public directory does not exist', function (): void {
        // No public/ dir — not a Laravel project
        $this->service->createHealthCheckFile();

        expect(file_exists($this->projectDir . '/public/health.php'))->toBeFalse();
    });

    it('does not overwrite existing health.php', function (): void {
        mkdir($this->projectDir . '/public', 0755);
        file_put_contents($this->projectDir . '/public/health.php', 'custom content');

        $this->service->createHealthCheckFile();

        expect(file_get_contents($this->projectDir . '/public/health.php'))
            ->toBe('custom content');
    });
});

// ─── getFileList() ──────────────────────────────────────────────────────
// Inspects a stack source directory and returns a structured listing of
// what files it contains. Used to show users what will be installed.

describe('getFileList', function (): void {

    it('returns files organized by directory', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker/Dockerfile' => 'FROM php:8.4',
            'scripts/entrypoint-dev.sh' => '#!/bin/bash',
            'docker-compose.yml' => 'version: "3.8"',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files)
            ->toHaveKey('docker')
            ->toHaveKey('scripts')
            ->toHaveKey('root');
    });

    it('lists files inside directories as flat arrays', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker/Dockerfile' => 'FROM php:8.4',
            'docker/.dockerignore' => 'node_modules',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files['docker'])
            ->toBeArray()
            ->toContain('Dockerfile')
            ->toContain('.dockerignore');
    });

    it('lists root files under the root key', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker-compose.yml' => 'v: 3.8',
            'stack.json' => '{}',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files['root'])
            ->toContain('docker-compose.yml')
            ->toContain('stack.json');
    });

    it('only includes known individual files in root', function (): void {
        // random-file.txt is NOT in the known list — should be excluded
        createStackStructure($this->stackDir, [], [
            'docker-compose.yml' => 'v: 3.8',
            'random-file.txt' => 'ignored',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files['root'])->toContain('docker-compose.yml');
        expect($files['root'])->not->toContain('random-file.txt');
    });

    it('returns empty array when stack has no matching directories or files', function (): void {
        // Stack dir exists but has nothing the copier cares about
        createStackStructure($this->stackDir, ['other'], [
            'readme.md' => 'hello',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files)->toBeEmpty();
    });

    it('includes nested subdirectories in listing', function (): void {
        createStackStructure($this->stackDir, [], [
            'docker/Dockerfile' => 'FROM php:8.4',
            'docker/conf/nginx.conf' => 'server { }',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files['docker'])
            ->toHaveKey('conf')
            ->toContain('Dockerfile');

        expect($files['docker']['conf'])
            ->toContain('nginx.conf');
    });

    it('handles environments directory', function (): void {
        createStackStructure($this->stackDir, [], [
            'environments/.env.dev.example' => 'APP_ENV=dev',
            'environments/.env.staging.example' => 'APP_ENV=staging',
        ]);

        $files = $this->service->getFileList($this->stackDir);

        expect($files)
            ->toHaveKey('environments')
            ->and($files['environments'])
            ->toContain('.env.dev.example')
            ->toContain('.env.staging.example');
    });
});
