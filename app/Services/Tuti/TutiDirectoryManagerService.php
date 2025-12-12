<?php

declare(strict_types=1);

namespace App\Services\Tuti;

use RuntimeException;

final class TutiDirectoryManagerService
{
    /**
     * Project root directory
     */
    private string $projectRoot;

    public function __construct(? string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? base_path();
    }

    /**
     * Get .tuti directory path
     */
    public function getTutiPath(?string $path = null): string
    {
        return tuti_path($path, $this->projectRoot);
    }

    /**
     * Check if .tuti directory exists
     */
    public function exists(): bool
    {
        return tuti_exists($this->projectRoot);
    }

    /**
     * Initialize .tuti directory structure
     *
     * @return void
     * @throws RuntimeException If .tuti already exists
     */
    public function initialize(): bool
    {
        if ($this->exists()) {
            throw new RuntimeException(
                'Project already initialized. .tuti directory already exists.'
            );
        }

        $this->createBaseStructure();

        return true;
    }

    /**
     * Create base .tuti directory structure
     *
     * @return void
     */
    private function createBaseStructure(): void
    {
        $directories = [
            '',                 // .tuti itself
            'docker',
            'environments',
            'scripts',
        ];

        foreach ($directories as $dir) {
            $path = $this->getTutiPath($dir);

            if (!  is_dir($path)) {
                if (!  mkdir($path, 0755, true)) {
                    throw new RuntimeException("Failed to create directory: {$path}");
                }
            }
        }

        // TODO: Need create via StackJsonMetadataManagerService
//        $manifestPath = $this->getTutiPath('tuti.json');
//        file_put_contents($manifestPath, json_encode([
//            'version' => '1.0.0',
//            'created_at' => now()->toJSON(),
//        ], JSON_PRETTY_PRINT));
    }

    /**
     * Get all directories that should exist in .tuti
     *
     * @return array<int, string>
     */
    public function getRequiredDirectories(): array
    {
        return [
            'docker',
            'environments',
            'scripts',
        ];
    }

    /**
     * Validate .tuti directory structure
     *
     * @return bool
     */
    public function validate(): bool
    {
        if (! $this->exists()) {
            return false;
        }

        foreach ($this->getRequiredDirectories() as $dir) {
            if (! is_dir($this->getTutiPath($dir))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clean/remove .tuti directory (use with caution)
     *
     * @return bool
     */
    public function clean(): bool
    {
        $tutiPath = $this->getTutiPath();

        if (! is_dir($tutiPath)) {
            return true;
        }

        return $this->removeDirectory($tutiPath);
    }

    /**
     * Recursively remove directory
     *
     * @param string $directory
     * @return bool
     */
    private function removeDirectory(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $path = $directory .  DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    /**
     * Get project root directory
     */
    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }
}
