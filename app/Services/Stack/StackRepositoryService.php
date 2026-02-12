<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Storage\JsonFileService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Manages stack templates from remote repositories.
 *
 * Stacks are cached in ~/.tuti/stacks/ for offline use and performance.
 * This service handles downloading, updating, and locating stack templates.
 */
final class StackRepositoryService
{
    private const string REGISTRY_PATH = 'stacks/registry.json';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $registry = null;

    public function __construct(
        private readonly JsonFileService $jsonService
    ) {}

    /**
     * Get the path to a stack, downloading if necessary.
     *
     * @param  string  $stackName  Stack identifier (e.g., 'laravel')
     * @param  bool  $forceUpdate  Force re-download even if cached
     * @return string Path to the stack directory
     *
     * @throws RuntimeException If stack not found or download fails
     */
    public function getStackPath(string $stackName, bool $forceUpdate = false): string
    {
        // Check multiple possible local paths for bundled stacks
        $localPaths = [
            stack_path($stackName),                    // stubs/stacks/laravel
            stack_path("{$stackName}-stack"),          // stubs/stacks/laravel-stack
        ];

        foreach ($localPaths as $localPath) {
            if (is_dir($localPath) && file_exists($localPath . '/stack.json')) {
                return $localPath;
            }
        }

        // Check global cache
        $cachedPath = $this->getCachedStackPath($stackName);
        if (!is_dir($cachedPath)) {
            // Need to download
            return $this->downloadStack($stackName);
        }
        if ($forceUpdate) {
            // Need to download
            return $this->downloadStack($stackName);
        }
        // Verify it's valid
        if (file_exists($cachedPath . '/stack.json')) {
            return $cachedPath;
        }

        // Need to download
        return $this->downloadStack($stackName);
    }

    /**
     * Download a stack from its repository.
     */
    public function downloadStack(string $stackName): string
    {
        $stackInfo = $this->getStackInfo($stackName);

        if ($stackInfo === null) {
            throw new RuntimeException("Stack not found in registry: {$stackName}");
        }

        $repository = $stackInfo['repository'] ?? null;

        if ($repository === null) {
            throw new RuntimeException("Stack '{$stackName}' has no repository defined");
        }

        $targetPath = $this->getCachedStackPath($stackName);
        $branch = $stackInfo['branch'] ?? 'main';

        // Ensure global .tuti directory exists first
        $this->ensureGlobalTutiDirectoryExists();

        // Ensure parent directory exists
        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir) && (!@mkdir($parentDir, 0755, true) && ! is_dir($parentDir))) {
            throw new RuntimeException(
                "Failed to create directory: {$parentDir}. Check permissions for: " . global_tuti_path()
            );
        }

        // Remove existing if present
        if (is_dir($targetPath)) {
            $this->removeDirectory($targetPath);
        }

        // Clone the repository
        $result = Process::run(['git', 'clone', '--depth', '1', '--branch', $branch, $repository, $targetPath]);

        if (! $result->successful()) {
            throw new RuntimeException(
                "Failed to clone stack repository: {$result->errorOutput()}"
            );
        }

        // Remove .git directory to save space
        $gitDir = $targetPath . '/.git';
        if (is_dir($gitDir)) {
            $this->removeDirectory($gitDir);
        }

        // Validate the downloaded stack
        if (! file_exists($targetPath . '/stack.json')) {
            $this->removeDirectory($targetPath);
            throw new RuntimeException(
                'Downloaded stack is invalid: missing stack.json'
            );
        }

        return $targetPath;
    }

    /**
     * Update a cached stack to the latest version.
     */
    public function updateStack(string $stackName): string
    {
        return $this->downloadStack($stackName);
    }

    /**
     * Check if a stack is available (either local or in registry).
     */
    public function hasStack(string $stackName): bool
    {
        // Check local first
        $localPath = stack_path("{$stackName}-stack");
        if (is_dir($localPath) && file_exists($localPath . '/stack.json')) {
            return true;
        }

        // Check registry
        return $this->getStackInfo($stackName) !== null;
    }

    /**
     * Get information about a stack from the registry.
     *
     * @return array<string, mixed>|null
     */
    public function getStackInfo(string $stackName): ?array
    {
        $registry = $this->loadRegistry();

        return $registry['stacks'][$stackName] ?? null;
    }

    /**
     * Get all available stacks.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableStacks(): array
    {
        $stacks = [];

        // Add stacks from registry
        $registry = $this->loadRegistry();
        foreach ($registry['stacks'] ?? [] as $name => $info) {
            $stacks[$name] = $info;
            $stacks[$name]['source'] = 'registry';
            $stacks[$name]['cached'] = is_dir($this->getCachedStackPath($name));
        }

        // Add local stacks (for development)
        $localStacksDir = stack_path();
        if (is_dir($localStacksDir)) {
            $dirs = glob($localStacksDir . '/*-stack', GLOB_ONLYDIR);
            foreach ($dirs ?: [] as $dir) {
                $stackJsonPath = $dir . '/stack.json';
                if (file_exists($stackJsonPath)) {
                    $manifest = $this->jsonService->read($stackJsonPath);
                    $name = str_replace('-stack', '', basename($dir));

                    if (! isset($stacks[$name])) {
                        $stacks[$name] = [
                            'name' => $manifest['name'] ?? $name,
                            'description' => $manifest['description'] ?? '',
                            'framework' => $manifest['framework'] ?? 'unknown',
                            'type' => $manifest['type'] ?? 'unknown',
                            'source' => 'local',
                            'cached' => true,
                        ];
                    }
                }
            }
        }

        return $stacks;
    }

    /**
     * Get the cache path for a stack.
     */
    public function getCachedStackPath(string $stackName): string
    {
        return global_tuti_path("stacks/{$stackName}-stack");
    }

    /**
     * Clear the stack cache.
     */
    public function clearCache(?string $stackName = null): void
    {
        if ($stackName !== null) {
            $path = $this->getCachedStackPath($stackName);
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }

            return;
        }

        // Clear all cached stacks
        $cachePath = global_tuti_path('stacks');
        if (is_dir($cachePath)) {
            $this->removeDirectory($cachePath);
        }
    }

    /**
     * Load the stacks registry.
     *
     * @return array<string, mixed>
     */
    private function loadRegistry(): array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        $registryPath = stub_path(self::REGISTRY_PATH);

        if (! file_exists($registryPath)) {
            return $this->registry = ['stacks' => []];
        }

        return $this->registry = $this->jsonService->read($registryPath);
    }

    /**
     * Ensure the global ~/.tuti directory exists.
     */
    private function ensureGlobalTutiDirectoryExists(): void
    {
        $globalPath = global_tuti_path();

        if (is_dir($globalPath)) {
            return;
        }

        // Try to create it
        if (@mkdir($globalPath, 0755, true)) {
            // Also create subdirectories
            @mkdir($globalPath . '/stacks', 0755, true);
            @mkdir($globalPath . '/cache', 0755, true);
            @mkdir($globalPath . '/logs', 0755, true);

            return;
        }

        // If still doesn't exist, throw helpful error
        if (! is_dir($globalPath)) {
            throw new RuntimeException(
                "Failed to create global tuti directory: ~{$globalPath}. " .
                "Please run 'tuti install' first or create the directory manually: mkdir -p {$globalPath}"
            );
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        foreach ($items ?: [] as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $itemPath = $path . '/' . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
