<?php

declare(strict_types=1);

namespace App\Services\Support\EnvHandlers;

use App\Services\Support\EnvFileService;

/**
 * Handles .env file operations for standard WordPress projects.
 * Standard WordPress doesn't use .env natively - this is for Docker Compose only.
 */
final readonly class WordPressEnvHandler
{
    public function __construct(
        private EnvFileService $envService,
    ) {}

    /**
     * Detect if this is a standard WordPress project (not Bedrock).
     */
    public function detect(string $directory): bool
    {
        $directory = mb_rtrim($directory, '/');

        // Has wp-config.php or wp-config-sample.php but not Bedrock structure
        $hasWpConfig = file_exists($directory . '/wp-config.php')
            || file_exists($directory . '/wp-config-sample.php');

        $isBedrock = file_exists($directory . '/config/application.php')
            && file_exists($directory . '/web/wp-config.php');

        return $hasWpConfig && ! $isBedrock;
    }

    /**
     * Configure .env for WordPress Docker environment.
     * Standard WordPress uses .env for Docker Compose only (not native to WordPress).
     * Creates .env file with Docker/Tuti variables if it doesn't exist.
     *
     * @param  array<string, mixed>  $options
     */
    public function configure(string $directory, string $projectName, array $options = []): bool
    {
        // Standard WordPress doesn't have .env concept
        // We create one specifically for Docker Compose configuration

        if (! $this->envService->exists($directory)) {
            // Create empty .env file, then append Tuti section
            $this->envService->write($directory, '');
        }

        // Append Tuti Docker configuration section
        return $this->envService->appendTutiSection($directory, $projectName, [
            'stack_type' => 'wordpress',
            'php_version' => $options['php_version'] ?? '8.3',
        ]);
    }
}
