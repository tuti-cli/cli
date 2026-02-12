<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Services\Context\WorkingDirectoryService;
use Exception;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Service ProjectDirectoryService
 *
 * Manages .tuti directory structure within the user's project.
 */
final readonly class ProjectDirectoryService
{
    public function __construct(
        private WorkingDirectoryService $workingDirectory
    ) {}

    /**
     * Set the root directory for initialization.
     * This allows initializing in a different directory than cwd.
     */
    public function setInitializationRoot(string $path): void
    {
        $this->workingDirectory->setWorkingDirectory($path);
    }

    /**
     * Get the project root (where .tuti will be created).
     */
    public function getProjectRoot(): string
    {
        return $this->workingDirectory->getWorkingDirectory();
    }

    /**
     * Get the .tuti directory path.
     */
    public function getTutiPath(?string $subPath = null): string
    {
        return $this->workingDirectory->getTutiPath($subPath);
    }

    /**
     * Check if project is initialized (.tuti exists).
     */
    public function exists(): bool
    {
        return $this->workingDirectory->tutiExists();
    }

    /**
     * Create the .tuti directory structure.
     */
    public function create(): void
    {
        $tutiPath = $this->getTutiPath();

        if ($this->exists()) {
            throw new RuntimeException('.tuti directory already exists.');
        }

        if (! File::isDirectory($tutiPath)) {
            try {
                File::makeDirectory($tutiPath, 0755, true);
            } catch (Exception $e) {
                throw new RuntimeException("Failed to create .tuti directory at: {$tutiPath}. " .
                "Error: {$e->getMessage()}. " .
                "Please check write permissions for: {$this->getProjectRoot()}", $e->getCode(), $e);
            }
        }
    }

    /**
     * Create subdirectories within .tuti.
     *
     * @param  array<int, string>  $directories
     */
    public function createSubDirectories(array $directories = ['docker', 'environments', 'scripts']): void
    {
        foreach ($directories as $dir) {
            $path = $this->getTutiPath($dir);

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Validate the .tuti directory structure.
     */
    public function validate(): bool
    {
        if (! $this->exists()) {
            return false;
        }

        // Check required files/directories exist
        $required = [
            'config.json',
        ];

        foreach ($required as $item) {
            if (! file_exists($this->getTutiPath($item))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clean/remove the .tuti directory.
     */
    public function clean(): void
    {
        $tutiPath = $this->getTutiPath();

        if (! $this->exists()) {
            return;
        }

        File::deleteDirectory($tutiPath);
    }

    /**
     * Validate we're in a valid project directory.
     */
    public function validateProjectDirectory(): void
    {
        $projectRoot = $this->getProjectRoot();

        $indicators = [
            'composer.json',
            'package.json',
            'artisan',
            '.git',
        ];

        foreach ($indicators as $file) {
            if (file_exists($projectRoot . '/' . $file)) {
                return;
            }
        }

        throw new RuntimeException(
            "This doesn't appear to be a project directory. " .
            "Please run 'tuti init' from your project root."
        );
    }
}
