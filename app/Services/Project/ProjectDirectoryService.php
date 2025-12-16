<?php

declare(strict_types=1);

namespace App\Services\Project;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Service ProjectDirectoryService
 *
 * Responsible for handling project file structure and paths.
 * It strictly adheres to the convention that a project is defined by a
 * `.tuti` directory or `tuti.json` file.
 */
final class ProjectDirectoryService
{
    private ?string $initializationRoot = null;

    public function setInitializationRoot(?string $path): void
    {
        $this->initializationRoot = $path;
    }

    /**
     * Get the root directory of the current project.
     */
    public function getProjectRoot(): string
    {
        if ($this->initializationRoot !== null) {
            return $this->initializationRoot;
        }

        $root = base_path();

        if (file_exists($root . '/tuti.json') || is_dir($root . '/.tuti')) {
            return $root;
        }

        throw new RuntimeException("Not in a Tuti project. Run 'tuti init' first.");
    }

    /**
     * Get the path to the .tuti directory
     */
    public function getTutiDir(): string
    {
        return tuti_path(null, $this->getProjectRoot());
    }

    /**
     * Get path to a file inside .tuti directory
     */
    public function getTutiPath(string $path = ''): string
    {
        return tuti_path($path, $this->getProjectRoot());
    }

    /**
     * Check if .tuti directory exists
     */
    public function exists(): bool
    {
        try {
            return is_dir($this->getTutiDir());
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Initialize .tuti directory structure
     */
    public function initialize(): bool
    {
        if ($this->exists()) {
            throw new RuntimeException('Project already initialized. .tuti directory already exists.');
        }

        $this->createBaseStructure();

        return true;
    }

    /**
     * Get all directories that should exist in .tuti
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
     * Clean/remove the .tuti directory
     */
    public function clean(): void
    {
        $tutiPath = $this->getTutiDir();

        if (! is_dir($tutiPath)) {
            return;
        }

        $this->removeDirectory($tutiPath);
    }

    /**
     * Recursively remove a directory
     */
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

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Create base .tuti directory structure
     */
    private function createBaseStructure(): void
    {
        $directories = array_merge([''], $this->getRequiredDirectories());

        foreach ($directories as $dir) {
            $path = $this->getTutiPath($dir);

            if (! is_dir($path) && ! mkdir($path, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$path}");
            }
        }
    }
}
