<?php

declare(strict_types=1);

namespace App\Domain\Project\ValueObjects;

/**
 * Class ProjectConfiguration
 *
 * This Value Object encapsulates the configuration data of a project.
 * It's immutable-ish (readonly properties) to ensure consistency.
 *
 * Why separate this?
 * To keep the `Project` entity focused on identity and state, while this class
 * handles the parsing and access of specific setting values (like port mappings,
 * environment variables, etc.).
 */
final readonly class ProjectConfigurationVO
{
    /**
     * @param  array<string, mixed>  $environments
     * @param  array<string, mixed>  $rawConfig
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $version,
        public array $environments,
        public array $rawConfig = []
    ) {}

    /**
     * Factory method to create from raw array (e.g. from config.json)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['project']['name'] ?? 'unknown',
            type: $data['project']['type'] ?? 'unknown',
            version: $data['project']['version'] ?? '0.0.0',
            environments: $data['environments'] ?? [],
            rawConfig: $data
        );
    }
}
