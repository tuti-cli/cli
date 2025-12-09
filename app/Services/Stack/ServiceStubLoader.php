<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

final readonly class ServiceStubLoader
{
    /**
     * Load a stub file and replace placeholders
     *
     * @param string $stubPath Full path to stub file
     * @param array<string, string> $replacements Key-value pairs for placeholder replacement
     * @return string Processed stub content
     */
    public function load(string $stubPath, array $replacements = []): string
    {
        if (! file_exists($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read stub file: {$stubPath}");
        }

        return $this->replacePlaceholders($content, $replacements);
    }

    /**
     * Replace placeholders in content
     *
     * Placeholders format: {{PLACEHOLDER_NAME}}
     *
     * @param string $content Content with placeholders
     * @param array<string, string> $replacements Replacement values
     * @return string Processed content
     */
    private function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Check if content has unreplaced placeholders
     *
     * @return array<int, string> List of unreplaced placeholders
     */
    public function getUnreplacedPlaceholders(string $content): array
    {
        preg_match_all('/\{\{([A-Z_]+)\}\}/', $content, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Load multiple stubs and combine them
     *
     * @param array<string, array<string, string>> $stubs Array of ['path' => 'stub_path', 'replacements' => [... ]]
     * @return string Combined stub content
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
}
