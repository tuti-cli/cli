<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

trait CreatesHelperTestEnvironment
{
    protected string $testDir;
    protected string $stacksDir;
    protected array $tempDirs = [];

    protected function setupHelperEnvironment(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tuti-helper-test-' . uniqid();
        $this->stacksDir = $this->testDir . '/stacks';
        $this->tempDirs = [$this->testDir];

        mkdir($this->testDir);
        mkdir($this->stacksDir);

        // Override Laravel Zero's base path for testing
        $this->overrideBasePath($this->testDir);
    }

    protected function cleanupHelperEnvironment(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }

        $this->tempDirs = [];
    }

    protected function createStack(string $name, array $files = []): string
    {
        $stackPath = $this->stacksDir . '/' . $name;

        if (!is_dir($stackPath)) {
            mkdir($stackPath, 0755, true);
        }

        // Create default stack.json if not provided
        if (!isset($files['stack.json'])) {
            $files['stack.json'] = json_encode([
                'name' => $name,
                'description' => 'Test stack',
            ]);
        }

        foreach ($files as $filename => $content) {
            $filePath = $stackPath . '/' . $filename;
            $dir = dirname($filePath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($filePath, $content);
        }

        return $stackPath;
    }

    protected function createTutiDirectory(?string $withManifest = null): string
    {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath);

        if ($withManifest !== null) {
            file_put_contents($tutiPath . '/tuti.json', $withManifest);
        }

        return $tutiPath;
    }

    protected function overrideBasePath(string $path): void
    {
        // For Laravel Zero, we need to override the base path
        $app = app();

        // Method 1: Use reflection to set protected property
        $reflection = new \ReflectionClass($app);
        if ($reflection->hasProperty('basePath')) {
            $property = $reflection->getProperty('basePath');
            $property->setAccessible(true);
            $property->setValue($app, $path);
        }

        // Method 2: Bind path.base in container
        $app->instance('path.base', $path);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
