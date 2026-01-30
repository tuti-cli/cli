<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Project\ProjectDirectoryService;
use RuntimeException;

final readonly class StackFilesCopierService
{
    public function __construct(
        private ProjectDirectoryService $directoryManager
    ) {}

    public function copyFromStack(string $stackPath): bool
    {
        if (! is_dir($stackPath)) {
            throw new RuntimeException("Stack directory not found: {$stackPath}");
        }

        $this->copyDirectories($stackPath);
        $this->copyIndividualFiles($stackPath);
        $this->makeScriptsExecutable();

        return true;
    }

    public function getFileList(string $stackPath): array
    {
        $files = [];

        $directories = ['docker', 'environments', 'scripts'];

        foreach ($directories as $dir) {
            $path = $stackPath . '/' . $dir;

            if (is_dir($path)) {
                $files[$dir] = $this->getDirectoryFiles($path);
            }
        }

        $individualFiles = [
            'deploy.sh',
            'PREDEPLOYMENT-CHECKLIST.md',
            'stack.json',
            'docker-compose.yml',
            'docker-compose.dev.yml',
            'docker-compose.staging.yml',
            'docker-compose.prod.yml',
        ];

        foreach ($individualFiles as $file) {
            if (file_exists($stackPath . '/' . $file)) {
                $files['root'][] = $file;
            }
        }

        return $files;
    }

    private function copyDirectories(string $stackPath): void
    {
        $directories = [
            'docker',
            'environments',
            'scripts',
        ];

        foreach ($directories as $dir) {
            $source = $stackPath . '/' . $dir;
            $destination = $this->directoryManager->getTutiPath($dir);

            if (is_dir($source)) {
                $this->copyDirectory($source, $destination);
            }
        }
    }

    private function copyIndividualFiles(string $stackPath): void
    {
        $files = [
            'deploy.sh',
            'PREDEPLOYMENT-CHECKLIST.md',
            'stack.json',
            'docker-compose.yml',
            'docker-compose.dev.yml',
            'docker-compose.staging.yml',
            'docker-compose.prod.yml',
        ];

        foreach ($files as $file) {
            $source = $stackPath . '/' . $file;
            $destination = $this->directoryManager->getTutiPath($file);

            if (file_exists($source)) {
                if (! copy($source, $destination)) {
                    throw new RuntimeException("Failed to copy file: {$file}");
                }

                if (str_ends_with($file, '.sh')) {
                    chmod($destination, 0755);
                }
            }
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination) && ! mkdir($destination, 0755, true)) {
            throw new RuntimeException("Failed to create directory:  {$destination}");
        }

        $items = scandir($source);

        if ($items === false) {
            throw new RuntimeException("Failed to read directory: {$source}");
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $srcPath = $source . '/' . $item;
            $destPath = $destination . '/' . $item;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } elseif (! copy($srcPath, $destPath)) {
                throw new RuntimeException("Failed to copy:  {$item}");
            }
        }
    }

    private function makeScriptsExecutable(): void
    {
        $scriptsPath = $this->directoryManager->getTutiPath('scripts');

        if (! is_dir($scriptsPath)) {
            return;
        }

        $scripts = glob($scriptsPath . '/*.sh');

        if ($scripts === false) {
            return;
        }

        foreach ($scripts as $script) {
            chmod($script, 0755);
        }
    }

    private function getDirectoryFiles(string $directory): array
    {
        $files = [];
        $items = scandir($directory);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $files[$item] = $this->getDirectoryFiles($path);
            } else {
                $files[] = $item;
            }
        }

        return $files;
    }
}
