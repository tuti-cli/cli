<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

/**
 * Service JsonFileService
 *
 * Generic service for handling JSON file operations with variable substitution support.
 */
final class JsonFileService
{
    /**
     * Read and decode a JSON file.
     *
     * @param string $path Absolute path to the JSON file.
     * @param array<string, string> $variables Optional variables to substitute (e.g. ['{{USER}}' => 'me']).
     * @return array<string, mixed>
     * @throws RuntimeException If file not found or invalid JSON.
     */
    public function read(string $path, array $variables = []): array
    {
        if (!File::exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $content = File::get($path);

        if (!empty($variables)) {
            $content = $this->resolveVariables($content, $variables);
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid JSON in {$path}: " . $e->getMessage());
        }
    }

    /**
     * Write data to a JSON file.
     *
     * @param string $path Absolute path to the file.
     * @param array<string, mixed> $data Data to encode and write.
     */
    public function write(string $path, array $data): void
    {
        $dir = dirname($path);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            File::put($path, $json);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to encode JSON for {$path}: " . $e->getMessage());
        }
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        return File::exists($path);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        return File::delete($path);
    }

    /**
     * Resolve template variables in the content.
     */
    private function resolveVariables(string $content, array $variables): string
    {
        // Normalize keys to ensure they are wrapped in curly braces if passed raw
        // But simpler to expect caller to pass '{{KEY}}' => 'value' or just use keys as-is.
        // Let's support both {{VAR}} and {{ VAR }} spacing.

        $keys = array_keys($variables);
        $values = array_values($variables);

        // standard substitution
        return str_replace($keys, $values, $content);
    }
}
