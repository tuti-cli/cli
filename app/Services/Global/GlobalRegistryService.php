<?php

declare(strict_types=1);

namespace App\Services\Global;

use App\Services\Storage\JsonFileService;

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
    ) {}

    /**
     * Register or update a project in the registry.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(string $name, array $data): void
    {
        $registry = $this->load();

        // Merge existing data if any (preserve allocated ports if not provided)
        $existing = $registry['projects'][$name] ?? [];
        $registry['projects'][$name] = array_merge($existing, $data, [
            'last_accessed_at' => now()->toIso8601String(),
        ]);

        $this->save($registry);
    }

    /**
     * Get a project by name.
     *
     * @return array<string, mixed>|null
     */
    public function getProject(string $name): ?array
    {
        $registry = $this->load();

        return $registry['projects'][$name] ?? null;
    }

    /**
     * Get all registered projects.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $registry = $this->load();

        return $registry['projects'];
    }

    /**
     * Remove a project from the registry by name.
     */
    public function remove(string $name): bool
    {
        $registry = $this->load();

        if (! isset($registry['projects'][$name])) {
            return false;
        }

        unset($registry['projects'][$name]);
        $this->save($registry);

        return true;
    }

    /**
     * Get projects whose paths no longer exist on disk.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStaleProjects(): array
    {
        $stale = [];

        foreach ($this->all() as $name => $data) {
            if (! isset($data['path']) || ! is_dir($data['path'])) {
                $stale[$name] = $data;
            }
        }

        return $stale;
    }

    /**
     * Remove all stale projects from the registry.
     */
    public function pruneStale(): int
    {
        $stale = $this->getStaleProjects();

        if ($stale === []) {
            return 0;
        }

        $registry = $this->load();

        foreach (array_keys($stale) as $name) {
            unset($registry['projects'][$name]);
        }

        $this->save($registry);

        return count($stale);
    }

    /**
     * Load registry data.
     *
     * @return array{projects: array<string, array<string, mixed>>}
     */
    private function load(): array
    {
        $path = $this->getRegistryPath();

        if (! $this->jsonService->exists($path)) {
            return ['projects' => []];
        }

        return $this->jsonService->read($path);
    }

    /**
     * Save registry data.
     *
     * @param  array{projects: array<string, array<string, mixed>>}  $data
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

        if (! $home && isset($_SERVER['HOMEDRIVE'], $_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }

        return $home . '/.tuti/' . self::FILE_NAME;
    }
}
