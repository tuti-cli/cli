<?php

declare(strict_types=1);

namespace App\Services\Stack;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * ServiceStubLoader is responsible for loading and processing
 * stub files and replacing placeholders with actual values.
 */
final readonly class StackStubLoaderService
{
    /**
     * Load a stub file and replace placeholders.
     *
     * @param  string  $stubPath  Relative path (e.g., 'databases/postgres.stub') or absolute path
     * @param  array<string, string>  $replacements  Placeholder replacements
     */
    public function load(string $stubPath, array $replacements = []): string
    {
        $resolvedPath = $this->resolvePath($stubPath);

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath} (resolved: {$resolvedPath})");
        }

        $content = File::get($resolvedPath);

        return $this->replacePlaceholders($content, $replacements);
    }

    /**
     * Resolve the stub path to an absolute path.
     *
     * Supports:
     * - Absolute paths (returned as-is)
     * - Relative paths (resolved via stub_path())
     * - Paths with 'services/' prefix
     */
    private function resolvePath(string $stubPath): string
    {
        // If it's an absolute path, return as-is
        if (str_starts_with($stubPath, '/') || preg_match('/^[a-zA-Z]:/', $stubPath)) {
            return $stubPath;
        }

        // Build list of paths to try
        $pathsToTry = [
            stub_path('services/' . $stubPath),
            stub_path($stubPath),
        ];

        // Try each path
        foreach ($pathsToTry as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        // Return first path for error message
        return $pathsToTry[0];
    }

    /**
     * Check if content has unreplaced placeholders.
     *
     * @return array<int, string>
     */
    public function getUnreplacedPlaceholders(string $content): array
    {
        preg_match_all('/\{\{([A-Z_]+)\}\}/', $content, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Load multiple stubs and combine them.
     *
     * @param  array<int, array{path: string, replacements?: array<string, string>}>  $stubs
     */
    public function loadMultiple(array $stubs): string
    {
        $combined = '';

        foreach ($stubs as $stub) {
            $combined .= $this->load($stub['path'], $stub['replacements'] ?? []);
            $combined .= "\n";
        }

        return $combined;
    }

    /**
     * Replace placeholders in content.
     *
     * Placeholders format: {{PLACEHOLDER_NAME}}
     *
     * @param  array<string, string>  $replacements
     */
    private function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }
}
