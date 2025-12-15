<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Domain\Project\ValueObjects\ProjectConfigurationVO;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service ProjectMetadataService
 *
 * Context: Persistence
 * Responsibility: load() and save() the simplified ProjectConfiguration from/to JSON.
 */
final readonly class ProjectMetadataService
{
    public function __construct(
        private ProjectDirectoryService $directoryService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Load the current project configuration.
     */
    public function load(): ProjectConfigurationVO
    {
        $path = $this->directoryService->getTutiPath('config.json');

        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found at: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file at {$path}");
        }

        // Resolve any template variables in the content
        $content = $this->resolveVariables($content);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON in config file: ' . $e->getMessage());
        }

        return ProjectConfigurationVO::fromArray($data);
    }

    /**
     * Create a new project configuration file.
     *
     * @param  array<string, mixed>  $config
     */
    public function create(array $config): void
    {
        $configPath = $this->directoryService->getTutiPath('config.json');

        if (file_exists($configPath)) {
            throw new RuntimeException('Configuration file already exists');
        }

        file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Resolve template variables in the configuration content.
     */
    private function resolveVariables(string $content): string
    {
        $variables = [
            'SYSTEM_USER' => $this->getSystemUser(),
            'PROJECT_ROOT' => $this->directoryService->getProjectRoot(),
            // Add more variables as needed
        ];

        // Replace both {{ VAR }} and {{VAR}} formats
        foreach ($variables as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }

    /**
     * Get the current system user.
     */
    private function getSystemUser(): string
    {
        $user = getenv('USER');

        if ($user !== false && $user !== '') {
            return $user;
        }

        if (isset($_SERVER['USER']) && $_SERVER['USER'] !== '') {
            return $_SERVER['USER'];
        }

        return 'tuti';
    }

    /**
     * Check if project is initialized.
     */
    public function isInitialized(): bool
    {
        try {
            $this->directoryService->getProjectRoot();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
