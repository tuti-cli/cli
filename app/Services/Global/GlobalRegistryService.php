<?php

declare(strict_types=1);

namespace App\Services\Global;

use App\Services\Storage\JsonFileService;
use Illuminate\Support\Arr;

/**
 * Service GlobalRegistryService
 *
 * Manages the global project registry (~/.tuti/projects.json).
 * Acts as a local database of all known projects.
 */
final readonly class GlobalRegistryService
{
    private const string FILE_NAME = 'projects.json';

    public function __construct(
        private JsonFileService $jsonService
    ) {
    }

    /**
     * Register or update a project in the registry.
     */
    public function register(string $name, array $data): void
    {
        $registry = $this->load();

        // Ensure 'projects' key exists
        if (!isset($registry['projects'])) {
            $registry['projects'] = [];
        }

        // Merge existing data if any (preserve allocated ports if not provided)
        $existing = $registry['projects'][$name] ?? [];
        $registry['projects'][$name] = array_merge($existing, $data, [
            'last_accessed_at' => now()->toIso8601String(),
        ]);

        $this->save($registry);
    }

    /**
     * Get a project by name.
     */
    public function getProject(string $name): ?array
    {
        $registry = $this->load();

        return $registry['projects'][$name] ?? null;
    }

    /**
     * Get all registered projects.
     */
    public function all(): array
    {
        $registry = $this->load();

        return $registry['projects'] ?? [];
    }

    /**
     * Load registry data.
     */
    private function load(): array
    {
        $path = $this->getRegistryPath();

        if (!$this->jsonService->exists($path)) {
            return ['projects' => []];
        }

        return $this->jsonService->read($path);
    }

    /**
     * Save registry data.
     */
    private function save(array $data): void
    {
        $this->jsonService->write($this->getRegistryPath(), $data);
    }

    /**
     * Get path to projects.json
     */
    private function getRegistryPath(): string
    {
        $home = getenv('HOME');

        if (!$home && isset($_SERVER['HOMEDRIVE'], $_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }

        return $home . '/.tuti/' . self::FILE_NAME;
    }
}
