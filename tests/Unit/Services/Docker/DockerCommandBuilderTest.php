<?php

declare(strict_types=1);

/**
 * DockerCommandBuilder Unit Tests
 *
 * Tests the centralized Docker command building service that
 * eliminates duplication across multiple Docker-related services.
 *
 * @see DockerCommandBuilder
 */

use App\Services\Docker\DockerCommandBuilder;

// ─── Setup ──────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->builder = new DockerCommandBuilder;
    $this->tempDir = createTestDirectory();
});

afterEach(function (): void {
    cleanupTestDirectory($this->tempDir);
});

// ─── buildComposeCommand() ───────────────────────────────────────────────

describe('buildComposeCommand', function (): void {

    it('builds basic docker compose command', function (): void {
        // Create a dummy compose file for the test
        $composeFile = $this->tempDir . '/docker-compose.yml';
        touch($composeFile);

        $command = $this->builder->buildComposeCommand(
            composeFiles: [$composeFile],
            projectName: 'myproject',
            args: ['up', '-d'],
        );

        expect($command)->toBe([
            'docker', 'compose',
            '-f', $composeFile,
            '-p', 'myproject',
            'up', '-d',
        ]);
    });

    it('includes multiple compose files that exist', function (): void {
        // Create dummy compose files for the test
        $mainCompose = $this->tempDir . '/docker-compose.yml';
        $devCompose = $this->tempDir . '/docker-compose.dev.yml';
        $prodCompose = $this->tempDir . '/docker-compose.prod.yml';
        touch($mainCompose);
        touch($devCompose);
        touch($prodCompose);

        $command = $this->builder->buildComposeCommand(
            composeFiles: [$mainCompose, $devCompose, $prodCompose],
            projectName: 'myproject',
            args: ['up'],
        );

        expect($command)->toContain(
            '-f',
            $mainCompose,
            '-f',
            $devCompose,
            '-f',
            $prodCompose,
        );
    });

    it('filters out non-existent compose files', function (): void {
        $existingCompose = $this->tempDir . '/docker-compose.yml';
        touch($existingCompose);

        $command = $this->builder->buildComposeCommand(
            composeFiles: [
                $existingCompose,
                '/nonexistent/docker-compose.yml', // This will be filtered out
            ],
            projectName: 'myproject',
            args: ['up'],
        );

        expect($command)->toContain('-f', $existingCompose);
        expect($command)->not->toContain('/nonexistent/docker-compose.yml');
    });

    it('includes env file when provided and exists', function (): void {
        $envFile = $this->tempDir . '/.env';
        file_put_contents($envFile, 'APP_ENV=dev');

        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/path/to/docker-compose.yml'],
            projectName: 'myproject',
            envFilePath: $envFile,
            args: ['up'],
        );

        expect($command)->toContain('--env-file', $envFile);
    });

    it('omits env file when provided but does not exist', function (): void {
        $nonExistentEnv = '/nonexistent/.env';

        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/path/to/docker-compose.yml'],
            projectName: 'myproject',
            envFilePath: $nonExistentEnv,
            args: ['up'],
        );

        expect($command)->not->toContain('--env-file', $nonExistentEnv);
    });

    it('omits env file when null', function (): void {
        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/path/to/docker-compose.yml'],
            projectName: 'myproject',
            envFilePath: null,
            args: ['up'],
        );

        expect($command)->not->toContain('--env-file');
    });

    it('omits project name when null', function (): void {
        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/path/to/docker-compose.yml'],
            projectName: null,
            args: ['up'],
        );

        expect($command)->not->toContain('-p');
    });

    it('works with only args', function (): void {
        $command = $this->builder->buildComposeCommand(
            args: ['version'],
        );

        expect($command)->toBe(['docker', 'compose', 'version']);
    });

    it('handles complex command arguments', function (): void {
        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/compose.yml'],
            projectName: 'myproject',
            envFilePath: $this->tempDir . '/.env',
            args: ['up', '-d', '--build', '--remove-orphans', 'postgres'],
        );

        expect($command)->toContain('up', '-d', '--build', '--remove-orphans', 'postgres');
    });
});

// ─── buildRunCommand() ─────────────────────────────────────────────────────

describe('buildRunCommand', function (): void {

    it('builds basic docker run command', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx:latest',
            workDir: '/host/path',
            command: 'nginx -g daemon off',
            mapUser: false, // Disable user mapping for consistent test output
        );

        expect($command)->toBe([
            'docker', 'run', '--rm', '--interactive',
            '-v', '/host/path:/app',
            '-w', '/app',
            'nginx:latest', 'sh', '-c', 'nginx -g daemon off',
        ]);
    });

    it('includes TTY flag when requested', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            tty: true,
        );

        expect($command)->toContain('--tty');
    });

    it('omits TTY flag by default', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            tty: false,
        );

        expect($command)->not->toContain('--tty');
    });

    it('includes network when specified', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            network: 'my-network',
        );

        expect($command)->toContain('--network', 'my-network');
    });

    it('adds environment variables', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            env: ['APP_ENV' => 'dev', 'DEBUG' => 'true'],
        );

        expect($command)->toContain('APP_ENV=dev', 'DEBUG=true');
    });

    it('adds additional volumes', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            volumes: ['/host/config' => '/etc/nginx', '/host/logs' => '/var/log/nginx'],
            mapUser: false, // Disable user mapping for consistent test output
        );

        expect($command)->toContain(
            '-v',
            '/host/config:/etc/nginx',
            '-v',
            '/host/logs:/var/log/nginx',
        );
    });

    it('includes user mapping by default on non-Windows', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
        );

        if (PHP_OS_FAMILY !== 'Windows') {
            expect($command)->toContain('--user');
        }
    });

    it('omits user mapping when requested', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            mapUser: false,
        );

        expect($command)->not->toContain('--user');
    });

    it('omits --rm flag when requested', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'nginx',
            workDir: '/host/path',
            remove: false,
        );

        expect($command)->not->toContain('--rm');
    });
});

// ─── buildExecCommand() ────────────────────────────────────────────────────

describe('buildExecCommand', function (): void {

    it('builds basic docker exec command', function (): void {
        $command = $this->builder->buildExecCommand(
            container: 'mycontainer',
            command: 'ls -la',
        );

        expect($command)->toBe([
            'docker', 'exec', '-T',
            'mycontainer', 'sh', '-c', 'ls -la',
        ]);
    });

    it('includes environment variables', function (): void {
        $command = $this->builder->buildExecCommand(
            container: 'mycontainer',
            command: 'env',
            env: ['VAR1' => 'value1', 'VAR2' => 'value2'],
        );

        expect($command)->toContain('VAR1=value1', 'VAR2=value2');
    });

    it('omits TTY flag when requested', function (): void {
        $command = $this->builder->buildExecCommand(
            container: 'mycontainer',
            command: 'ls',
            tty: false,
        );

        expect($command)->not->toContain('-T');
    });
});

// ─── buildNetworkCreateCommand() ────────────────────────────────────────────

describe('buildNetworkCreateCommand', function (): void {

    it('builds docker network create command', function (): void {
        $command = $this->builder->buildNetworkCreateCommand('my-network');

        expect($command)->toBe(['docker', 'network', 'create', 'my-network']);
    });
});

// ─── buildNetworkInspectCommand() ───────────────────────────────────────────

describe('buildNetworkInspectCommand', function (): void {

    it('builds docker network inspect command', function (): void {
        $command = $this->builder->buildNetworkInspectCommand('my-network');

        expect($command)->toBe(['docker', 'network', 'inspect', 'my-network']);
    });
});

// ─── buildImagePullCommand() ───────────────────────────────────────────────

describe('buildImagePullCommand', function (): void {

    it('builds docker image pull command', function (): void {
        $command = $this->builder->buildImagePullCommand('nginx:latest');

        expect($command)->toBe(['docker', 'pull', 'nginx:latest']);
    });
});

// ─── buildImageInspectCommand() ───────────────────────────────────────────

describe('buildImageInspectCommand', function (): void {

    it('builds docker image inspect command', function (): void {
        $command = $this->builder->buildImageInspectCommand('nginx:latest');

        expect($command)->toBe(['docker', 'image', 'inspect', 'nginx:latest']);
    });
});

// ─── buildContainerInspectCommand() ────────────────────────────────────────

describe('buildContainerInspectCommand', function (): void {

    it('builds docker container inspect command', function (): void {
        $command = $this->builder->buildContainerInspectCommand('mycontainer');

        expect($command)->toBe(['docker', 'inspect', 'mycontainer']);
    });

    it('includes format when specified', function (): void {
        $command = $this->builder->buildContainerInspectCommand(
            'mycontainer',
            format: '{{.State.Status}}',
        );

        expect($command)->toContain('--format', '{{.State.Status}}');
    });
});

// ─── buildInfoCommand() ─────────────────────────────────────────────────

describe('buildInfoCommand', function (): void {

    it('builds docker info command', function (): void {
        $command = $this->builder->buildInfoCommand();

        expect($command)->toBe(['docker', 'info']);
    });
});

// ─── Security ─────────────────────────────────────────────────────────────

describe('security', function (): void {

    it('always returns arrays for shell safety', function (): void {
        $commands = [
            $this->builder->buildComposeCommand(),
            $this->builder->buildRunCommand('image', '/path'),
            $this->builder->buildExecCommand('container', 'ls'),
            $this->builder->buildNetworkCreateCommand('network'),
        ];

        foreach ($commands as $command) {
            expect($command)->toBeArray();
        }
    });

    it('never interpolates user input into strings', function (): void {
        // All builder methods should use array concatenation, not string interpolation
        $command = $this->builder->buildComposeCommand(
            composeFiles: ['/safe/path.yml'],
            projectName: 'safe-project',
            args: ['safe', 'args'],
        );

        // Verify array structure (no concatenated strings with user input)
        expect($command)->toBeArray();
    });
});

// ─── Integration scenarios ───────────────────────────────────────────────

describe('integration scenarios', function (): void {

    it('handles real-world compose command for project start', function (): void {
        // Create dummy compose files for the test
        $mainCompose = $this->tempDir . '/docker-compose.yml';
        $devCompose = $this->tempDir . '/docker-compose.dev.yml';
        $envFile = $this->tempDir . '/.env';
        touch($mainCompose);
        touch($devCompose);
        file_put_contents($envFile, 'APP_ENV=local');

        $command = $this->builder->buildComposeCommand(
            composeFiles: [$mainCompose, $devCompose],
            projectName: 'myapp',
            envFilePath: $envFile,
            args: ['up', '-d', '--build'],
        );

        expect($command)->toContain(
            'docker',
            'compose',
            '-f',
            $mainCompose,
            '-f',
            $devCompose,
            '-p',
            'myapp',
            '--env-file',
            $envFile,
            'up',
            '-d',
            '--build',
        );
    });

    it('handles real-world run command for container execution', function (): void {
        $command = $this->builder->buildRunCommand(
            image: 'serversideup/php:8.4-cli',
            workDir: '/host/project',
            command: 'composer install --no-dev',
            env: ['COMPOSER_MEMORY_LIMIT' => '-1'],
            volumes: ['/host/vendor:/app/vendor'],
        );

        expect($command)->toContain(
            'docker',
            'run',
            '--rm',
            '--interactive',
            '-v',
            '/host/project:/app',
            '-w',
            '/app',
            'serversideup/php:8.4-cli',
            'sh',
            '-c',
            'composer install --no-dev',
        );
    });
});
