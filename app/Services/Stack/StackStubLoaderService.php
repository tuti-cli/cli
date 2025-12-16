<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * ServiceStubLoader is responsible for loading and processing
 * stub files and replacing placeholders with actual values.
 */
final readonly class StackStubLoaderService
{
    /**
     * Load a stub file and replace placeholders
     */
    public function load(string $stubPath, array $replacements = []): string
    {
        if (!\Illuminate\Support\Facades\File::exists($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $content = \Illuminate\Support\Facades\File::get($stubPath);

        return $this->replacePlaceholders($content, $replacements);
    }

    /**
     * Check if content has unreplaced placeholders
     */
    public function getUnreplacedPlaceholders(string $content): array
    {
        preg_match_all('/\{\{([A-Z_]+)\}\}/', $content, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Load multiple stubs and combine them
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
     * Replace placeholders in content
     *
     * Placeholders format: {{PLACEHOLDER_NAME}}
     */
    private function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}
