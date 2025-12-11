<?php

declare(strict_types=1);

namespace App\Services\Tuti;

use RuntimeException;

final readonly class TutiJsonMetadataManagerService
{
    public function __construct(
        private TutiDirectoryManagerService $directoryManager
    ) {}

    /**
     * Create initial project metadata
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): void
    {
        if ($this->exists()) {
            throw new RuntimeException('Project metadata already exists.');
        }

        $metadata = [
            'version' => '1.0.0',
            'stack' => $data['stack'] ?? 'unknown',
            'stack_version' => $data['stack_version'] ?? '1.0.0',
            'project_name' => $data['project_name'] ?? 'myapp',
            'created_at' => now()->toJSON(),
            'updated_at' => now()->toJSON(),
            'services' => $data['services'] ??  [],
            'environments' => [
                'current' => $data['environment'] ?? 'dev',
                'configured' => [$data['environment'] ?? 'dev'],
            ],
            'features' => $data['features'] ??  [],
        ];

        $this->write($metadata);
    }

    /**
     * Load project metadata
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (! $this->exists()) {
            throw new RuntimeException(
                'Project not initialized. Run:  tuti stack:init'
            );
        }

        $path = $this->getPath();
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read metadata file: {$path}");
        }

        /** @var array<string, mixed> */
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Update project metadata
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): void
    {
        $metadata = $this->load();
        $metadata = array_merge($metadata, $data);
        $metadata['updated_at'] = now()->toJSON();

        $this->write($metadata);
    }

    /**
     * Check if metadata file exists
     */
    public function exists(): bool
    {
        return file_exists($this->getPath());
    }

    /**
     * Get metadata file path
     */
    public function getPath(): string
    {
        return $this->directoryManager->getTutiPath('tuti.json');
    }

    /**
     * Write metadata to file
     *
     * @param array<string, mixed> $metadata
     */
    private function write(array $metadata): void
    {
        $path = $this->getPath();
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode metadata as JSON');
        }

        $result = file_put_contents($path, $json);

        if ($result === false) {
            throw new RuntimeException("Failed to write metadata file: {$path}");
        }
    }

    /**
     * Get stack name from metadata
     */
    public function getStack(): string
    {
        return $this->load()['stack'];
    }

    /**
     * Get project name from metadata
     */
    public function getProjectName(): string
    {
        return $this->load()['project_name'];
    }

    /**
     * Get current environment from metadata
     */
    public function getCurrentEnvironment(): string
    {
        return $this->load()['environments']['current'];
    }

    /**
     * Get all configured services
     *
     * @return array<string, mixed>
     */
    public function getServices(): array
    {
        return $this->load()['services'];
    }

    /**
     * Set current environment
     */
    public function setCurrentEnvironment(string $environment): void
    {
        $metadata = $this->load();
        $metadata['environments']['current'] = $environment;

        if (! in_array($environment, $metadata['environments']['configured'], true)) {
            $metadata['environments']['configured'][] = $environment;
        }

        $metadata['updated_at'] = now()->toJSON();

        $this->write($metadata);
    }
}
