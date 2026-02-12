<?php

declare(strict_types=1);

namespace App\Services\Global;

use App\Services\Storage\JsonFileService;
use Illuminate\Support\Arr;

/**
 * Service GlobalSettingsService
 *
 * Manages the global user settings file (~/.tuti/settings.json).
 */
final readonly class GlobalSettingsService
{
    private const string FILE_NAME = 'settings.json';

    public function __construct(
        private JsonFileService $jsonService
    ) {}

    /**
     * Get a setting value using dot notation.
     *
     * @param  string  $key  Dot notation key (e.g. 'user.name') or null for all.
     * @param  mixed  $default  Default value if key missing.
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        $settings = $this->load();

        if ($key === null) {
            return $settings;
        }

        return Arr::get($settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value): void
    {
        $settings = $this->load();

        Arr::set($settings, $key, $value);

        $this->save($settings);
    }

    /**
     * Load all settings.
     *
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $path = $this->getSettingsPath();

        if (! $this->jsonService->exists($path)) {
            return [];
        }

        // We generally don't substitute variables in settings.json itself,
        // as it IS the source of variables.
        return $this->jsonService->read($path);
    }

    /**
     * Save settings to file.
     *
     * @param  array<string, mixed>  $settings
     */
    private function save(array $settings): void
    {
        $this->jsonService->write($this->getSettingsPath(), $settings);
    }

    /**
     * Get the absolute path to settings.json
     */
    private function getSettingsPath(): string
    {
        $home = getenv('HOME');

        if (! $home && isset($_SERVER['HOMEDRIVE'], $_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }

        return $home . '/.tuti/' . self::FILE_NAME;
    }
}
