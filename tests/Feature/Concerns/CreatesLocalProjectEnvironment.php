<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Services\Project\ProjectDirectoryService;
use Illuminate\Support\Facades\File;

/**
 * Trait CreatesLocalProjectEnvironment
 *
 * Provides reusable setup for testing Local commands.
 * Creates a minimal project structure with tuti directory and configjson.
 */
trait CreatesLocalProjectEnvironment
{
    protected string $testProjectDir;

    protected ProjectDirectoryService $projectDirService;

    protected function setupLocalProject(): void
    {
        // Create unique test directory
        $this->testProjectDir = sys_get_temp_dir() . '/tuti-local-test-' . uniqid();
        mkdir($this->testProjectDir, 0755, true);

        // Change to test directory (commands expect to run from project root)
        chdir($this->testProjectDir);

        // Initialize tuti directory structure
        $this->projectDirService = new ProjectDirectoryService();
        $this->projectDirService->initialize();

        // Create docker-compose.yml fixture
        $this->createDockerCompose();

        // Create config.json fixture
        $this->createProjectConfig();
    }

    protected function cleanupLocalProject(): void
    {
        if (isset($this->testProjectDir) && is_dir($this->testProjectDir)) {
            File::deleteDirectory($this->testProjectDir);
        }
    }

    protected function createDockerCompose(string $content = null): void
    {
        $dockerDir = $this->testProjectDir .  '/.tuti/docker';

        if (! is_dir($dockerDir)) {
            mkdir($dockerDir, 0755, true);
        }

        $defaultContent = <<<YAML
services:
  app:
    image: php:8.4-fpm
    container_name: test-app
    volumes:
      - :/var/www

  nginx:
    image: nginx:alpine
    container_name:  test-nginx
    ports:
      - "8080:80"
    depends_on:
      - app

  postgres:
    image: postgres:17-alpine
    container_name: test-postgres
    environment:
      POSTGRES_DB: test_db
      POSTGRES_USER: test_user
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
YAML;

        file_put_contents(
            $dockerDir . '/docker-compose.yml',
            $content ??  $defaultContent
        );
    }

    protected function createProjectConfig(array $config = []): void
    {
        $defaultConfig = [
            'project' => [
                'name' => 'test-project',
                'type' => 'laravel',
                'version' => '1.0.0',
            ],
            'environments' => [
                'local' => [
                    'domain' => 'test-project.test',
                ],
                'staging' => [
                    'domain' => 'staging.example.com',
                ],
            ],
        ];

        $mergedConfig = array_merge_recursive($defaultConfig, $config);

        file_put_contents(
            $this->testProjectDir . '/.tuti/configjson',
            json_encode($mergedConfig, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    protected function removeProjectConfig(): void
    {
        $configPath = $this->testProjectDir . '/.tuti/configjson';

        if (file_exists($configPath)) {
            unlink($configPath);
        }
    }

    protected function removeDockerCompose(): void
    {
        $composePath = $this->testProjectDir . '/.tuti/docker/docker-compose.yml';

        if (file_exists($composePath)) {
            unlink($composePath);
        }
    }
}
