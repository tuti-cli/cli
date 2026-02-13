<?php

declare(strict_types=1);

namespace App\Services\Stack;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * ServiceStubLoader is responsible for loading and processing
 * stub files and replacing placeholders with actual values.
 *
 * Supports section-based stubs with format:
 *   # @section: base
 *   # @section: dev
 *   # @section: volumes
 *   # @section: env
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
     * Load a specific section from a stub file.
     *
     * @param  string  $stubPath  Path to stub file
     * @param  string  $section  Section name (base, dev, prod, volumes, env)
     * @param  array<string, string>  $replacements  Placeholder replacements
     * @return string|null Section content or null if not found
     */
    public function loadSection(string $stubPath, string $section, array $replacements = []): ?string
    {
        $resolvedPath = $this->resolvePath($stubPath);

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath} (resolved: {$resolvedPath})");
        }

        $content = File::get($resolvedPath);
        $sections = $this->parseSections($content);

        if (! isset($sections[$section])) {
            return null;
        }

        return $this->replacePlaceholders($sections[$section], $replacements);
    }

    /**
     * Parse stub content into sections.
     *
     * @return array<string, string> Map of section name => content
     */
    public function parseSections(string $content): array
    {
        $sections = [];
        $currentSection = 'base'; // Default section if no marker
        $currentContent = [];

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Check for section marker: # @section: name
            if (preg_match('/^#\s*@section:\s*(\w+)\s*$/i', $line, $matches)) {
                // Save previous section if it has content
                if ($currentContent !== []) {
                    $sections[$currentSection] = mb_trim(implode("\n", $currentContent));
                }
                $currentSection = mb_strtolower($matches[1]);
                $currentContent = [];

                continue;
            }

            $currentContent[] = $line;
        }

        // Save last section
        if ($currentContent !== []) {
            $sections[$currentSection] = mb_trim(implode("\n", $currentContent));
        }

        return $sections;
    }

    /**
     * Check if a stub has sections.
     */
    public function hasSections(string $stubPath): bool
    {
        $resolvedPath = $this->resolvePath($stubPath);

        if (! File::exists($resolvedPath)) {
            return false;
        }

        $content = File::get($resolvedPath);

        return (bool) preg_match('/^#\s*@section:/m', $content);
    }

    /**
     * Get all section names from a stub.
     *
     * @return array<int, string>
     */
    public function getSectionNames(string $stubPath): array
    {
        $resolvedPath = $this->resolvePath($stubPath);

        if (! File::exists($resolvedPath)) {
            return [];
        }

        $content = File::get($resolvedPath);
        $sections = $this->parseSections($content);

        return array_keys($sections);
    }

    /**
     * Check if content has unreplaced placeholders.
     *
     * @return array<int, string>
     */
    public function getUnreplacedPlaceholders(string $content): array
    {
        preg_match_all('/\{\{([A-Z_]+)\}\}/', $content, $matches);

        return $matches[1];
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
     * Resolve the stub path to an absolute path.
     *
     * Supports:
     * - Absolute paths (returned as-is)
     * - PHAR paths (returned as-is)
     * - Relative paths (resolved via stub_path())
     */
    private function resolvePath(string $stubPath): string
    {
        // If it's an absolute path or PHAR path, return as-is
        if (str_starts_with($stubPath, '/')
            || str_starts_with($stubPath, 'phar://')
            || preg_match('/^[a-zA-Z]:/', $stubPath)
        ) {
            return $stubPath;
        }

        // Resolve relative path via stub_path helper
        return stub_path($stubPath);
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
