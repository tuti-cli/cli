<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Services\Project\ProjectDirectoryManagerService;
use App\Services\Stack\StackFilesCopierService;

trait CreatesTestStackEnvironment
{
    protected string $testDir;

    protected string $stackDir;

    protected array $tempDirs = [];

    protected ProjectDirectoryManagerService $manager;

    protected StackFilesCopierService $copier;

    protected function setupStackEnvironment(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tuti-copier-test-' . uniqid();
        $this->stackDir = sys_get_temp_dir() . '/tuti-stack-test-' . uniqid();
        $this->tempDirs = [];

        mkdir($this->testDir);
        mkdir($this->stackDir);

        $this->createDefaultStackStructure();

        $this->manager = new ProjectDirectoryManagerService($this->testDir);
        $this->manager->initialize();

        $this->copier = new StackFilesCopierService($this->manager);
    }

    protected function cleanupStackEnvironment(): void
    {
        // First clean the manager's tuti directory completely
        if (isset($this->manager)) {
            $this->manager->clean();

            // Ensure complete removal of .tuti directory and its contents
            $tutiPath = $this->testDir . '/.tuti';
            if (is_dir($tutiPath)) {
                $this->removeDirectory($tutiPath);
            }
        }

        // Then remove the test directory
        if (isset($this->testDir) && is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }

        // Remove the stack directory
        if (isset($this->stackDir) && is_dir($this->stackDir)) {
            $this->removeDirectory($this->stackDir);
        }

        // Remove any temporary directories created during tests
        foreach ($this->tempDirs as $tempDir) {
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
        }

        $this->tempDirs = [];
    }

    protected function createDefaultStackStructure(): void
    {
        mkdir($this->stackDir . '/docker');
        mkdir($this->stackDir . '/environments');
        mkdir($this->stackDir . '/scripts');

        file_put_contents($this->stackDir . '/docker/Dockerfile', 'FROM php:8.4');
        file_put_contents($this->stackDir . '/environments/.env.dev.example', 'APP_ENV=dev');
        file_put_contents($this->stackDir . '/environments/.env.prod.example', 'APP_ENV=production');
        file_put_contents($this->stackDir . '/scripts/deploy.sh', '#!/bin/bash');
        file_put_contents($this->stackDir . '/scripts/health-check.sh', '#!/bin/bash');
        file_put_contents($this->stackDir . '/deploy.sh', '#!/bin/bash');
        file_put_contents($this->stackDir . '/stack.json', '{"name":"test-stack"}');
        file_put_contents($this->stackDir . '/PREDEPLOYMENT-CHECKLIST.md', '# Checklist');
    }

    protected function createStackWithoutDocker(): string
    {
        $tempStackDir = $this->createTempStackDir('missing');

        mkdir($tempStackDir . '/environments');
        mkdir($tempStackDir . '/scripts');

        file_put_contents($tempStackDir . '/environments/.env.dev.example', 'APP_ENV=dev');
        file_put_contents($tempStackDir . '/scripts/deploy.sh', '#!/bin/bash');
        file_put_contents($tempStackDir . '/stack.json', '{"name":"test-stack"}');

        return $tempStackDir;
    }

    protected function createStackWithNestedStructure(): string
    {
        $tempStackDir = $this->createTempStackDir('nested');

        mkdir($tempStackDir . '/docker');
        mkdir($tempStackDir . '/docker/configs');
        mkdir($tempStackDir . '/environments');
        mkdir($tempStackDir . '/scripts');

        file_put_contents($tempStackDir . '/docker/Dockerfile', 'FROM php:8.4');
        file_put_contents($tempStackDir . '/docker/configs/nginx.conf', 'server {}');
        file_put_contents($tempStackDir . '/environments/.env.dev.example', 'APP_ENV=dev');
        file_put_contents($tempStackDir . '/scripts/deploy.sh', '#!/bin/bash');
        file_put_contents($tempStackDir . '/stack.json', '{"name":"test-stack"}');

        return $tempStackDir;
    }

    protected function createCustomStack(array $structure): string
    {
        $tempStackDir = $this->createTempStackDir('custom');

        foreach ($structure['directories'] ?? [] as $dir) {
            $fullPath = $tempStackDir . '/' . $dir;
            if (! is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }

        foreach ($structure['files'] ?? [] as $file => $content) {
            $fullPath = $tempStackDir . '/' . $file;
            $dir = dirname($fullPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, $content);
        }

        return $tempStackDir;
    }

    protected function ensureCleanTutiDirectory(): void
    {
        $tutiPath = $this->testDir . '/.tuti';
        if (is_dir($tutiPath)) {
            $this->removeDirectory($tutiPath);
        }

        // Reinitialize the manager to create a fresh .tuti directory
        $this->manager->initialize();
    }

    protected function createIsolatedEnvironment(): array
    {
        $isolatedTestDir = sys_get_temp_dir() . '/tuti-isolated-' . uniqid();
        mkdir($isolatedTestDir);
        $this->tempDirs[] = $isolatedTestDir;

        $isolatedManager = new ProjectDirectoryManagerService($isolatedTestDir);
        $isolatedManager->initialize();

        $isolatedCopier = new StackFilesCopierService($isolatedManager);

        return [
            'testDir' => $isolatedTestDir,
            'manager' => $isolatedManager,
            'copier' => $isolatedCopier,
        ];
    }

    private function createTempStackDir(string $suffix): string
    {
        $tempStackDir = sys_get_temp_dir() . '/tuti-stack-' . $suffix . '-' . uniqid();
        mkdir($tempStackDir);
        $this->tempDirs[] = $tempStackDir;

        return $tempStackDir;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
