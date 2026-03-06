<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Stack\Installers\Laravel;

use App\Services\Stack\Installers\Laravel\LaravelDatabaseConfigurator;

describe('LaravelDatabaseConfigurator', function (): void {
    beforeEach(function (): void {
        $this->configurator = new LaravelDatabaseConfigurator();
        $this->tempDir = sys_get_temp_dir() . '/tuti_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function (): void {
        if (is_dir($this->tempDir)) {
            removeDirectory($this->tempDir);
        }
    });

    describe('configureDefaultDatabaseConnection', function (): void {
        it('configures PostgreSQL correctly', function (): void {
            createEnvFiles($this->tempDir, 'mysql');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.postgres',
                'my-laravel-app'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            expect($envContent)->toContain('DB_CONNECTION=pgsql');
            expect($envContent)->toContain('DB_HOST=db');
            expect($envContent)->toContain('DB_PORT=5432');
            expect($envContent)->toContain('DB_DATABASE=my_laravel_app');
            expect($envContent)->toContain('DB_USERNAME=tuti');
            expect($envContent)->toContain('DB_PASSWORD=secret');
        });

        it('configures MySQL correctly', function (): void {
            createEnvFiles($this->tempDir, 'sqlite');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.mysql',
                'test-project'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            expect($envContent)->toContain('DB_CONNECTION=mysql');
            expect($envContent)->toContain('DB_HOST=db');
            expect($envContent)->toContain('DB_PORT=3306');
            expect($envContent)->toContain('DB_DATABASE=test_project');
        });

        it('configures MariaDB correctly', function (): void {
            createEnvFiles($this->tempDir, 'mysql');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.mariadb',
                'project'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            expect($envContent)->toContain('DB_CONNECTION=mariadb');
            expect($envContent)->toContain('DB_HOST=db');
        });

        it('comments out config for SQLite', function (): void {
            createEnvFiles($this->tempDir, 'mysql');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.sqlite',
                'test-project'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            expect($envContent)->toContain('# DB_HOST=127.0.0.1');
            expect($envContent)->toContain('# DB_PORT=3306');
            expect($envContent)->toContain('# DB_DATABASE=laravel');
        });

        it('updates both .env and .env.example', function (): void {
            createEnvFiles($this->tempDir, 'sqlite');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.postgres',
                'test-project'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            $envExampleContent = file_get_contents($this->tempDir . '/.env.example');

            expect($envContent)->toContain('DB_CONNECTION=pgsql');
            expect($envExampleContent)->toContain('DB_CONNECTION=pgsql');
        });

        it('converts hyphens to underscores in database names', function (): void {
            createEnvFiles($this->tempDir, 'sqlite');

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.postgres',
                'my-awesome-laravel-project'
            );

            $envContent = file_get_contents($this->tempDir . '/.env');
            expect($envContent)->toContain('DB_DATABASE=my_awesome_laravel_project');
        });

        it('handles missing .env file gracefully', function (): void {
            // Don't create .env files

            $this->configurator->configureDefaultDatabaseConnection(
                $this->tempDir,
                'databases.postgres',
                'test-project'
            );

            // Should not throw
            expect(true)->toBeTrue();
        });
    });
});

// Helper functions
function createEnvFiles(string $dir, string $dbConnection): void
{
    $envContent = <<<ENV
APP_NAME=Laravel
APP_ENV=local
DB_CONNECTION={$dbConnection}
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
ENV;

    file_put_contents($dir . '/.env', $envContent);
    file_put_contents($dir . '/.env.example', $envContent);
}

function removeDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object !== '.' && $object !== '..') {
            $path = $dir . '/' . $object;
            if (is_dir($path)) {
                removeDirectory($path);
            } else {
                unlink($path);
            }
        }
    }

    rmdir($dir);
}
