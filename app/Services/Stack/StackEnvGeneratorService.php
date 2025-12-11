<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

final readonly class StackEnvGeneratorService
{
    /**
     * Generate environment file from template and service requirements
     *
     * @param string $templatePath Path to . env.example template
     * @param array<int, string> $selectedServices Selected services
     * @param array<string, string> $projectConfig Project configuration
     * @param string $outputPath Output path for . env file
     */
    public function generate(
        string $templatePath,
        array $selectedServices,
        array $projectConfig,
        string $outputPath
    ): void {
        if (! file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }

        $content = file_get_contents($templatePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read template:  {$templatePath}");
        }

        // Replace project-specific variables
        $content = $this->replaceProjectVariables($content, $projectConfig);

        // Generate secure values
        $content = $this->generateSecureValues($content);

        // Write to output
        $result = file_put_contents($outputPath, $content);

        if ($result === false) {
            throw new RuntimeException("Failed to write . env file: {$outputPath}");
        }
    }

    /**
     * @param array<string, string> $projectConfig
     */
    private function replaceProjectVariables(string $content, array $projectConfig): string
    {
        foreach ($projectConfig as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }

        return $content;
    }

    private function generateSecureValues(string $content): string
    {
        // Generate random passwords for CHANGE_THIS placeholders
        $content = preg_replace_callback(
            '/CHANGE_THIS(? :_IN_PRODUCTION)?/',
            fn (): string => $this->generateSecurePassword(),
            $content
        );

        return $content;
    }

    private function generateSecurePassword(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Check if .env file already exists
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }
}
