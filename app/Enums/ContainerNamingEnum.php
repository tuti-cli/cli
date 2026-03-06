<?php

declare(strict_types=1);

namespace App\Enums;

use LogicException;

/**
 * Enum for Docker container naming patterns.
 *
 * Provides consistent naming for containers, networks, and volumes
 * following the convention: {project}_{env}_{resource}
 *
 * @example
 *   ContainerNamingEnum::Network->key('myapp')       // "myapp_network"
 *   ContainerNamingEnum::Network->name('myapp')      // "myapp_dev_network"
 *   ContainerNamingEnum::Container->name('myapp', 'database')  // "myapp_dev_database"
 *   ContainerNamingEnum::Volume->name('myapp', 'postgres_data') // "myapp_dev_postgres_data"
 */
enum ContainerNamingEnum: string
{
    case Container = 'container';
    case Network = 'network';
    case Volume = 'volume';

    public const string DEFAULT_NETWORK = 'app_network';

    public const string DEFAULT_ENV = 'dev';

    /**
     * Get the network key (without environment).
     * Usage: ContainerNamingEnum::Network->key('myapp') → "myapp_network"
     */
    public function key(string $projectName): string
    {
        return match ($this) {
            self::Network => "{$projectName}_network",
            default => throw new LogicException('key() only available for Network'),
        };
    }

    /**
     * Get the full resource name with environment.
     * Usage: ContainerNamingEnum::Network->name('myapp') → "myapp_dev_network"
     * Usage: ContainerNamingEnum::Container->name('myapp', 'database') → "myapp_dev_database"
     * Usage: ContainerNamingEnum::Volume->name('myapp', 'postgres_data') → "myapp_dev_postgres_data"
     */
    public function name(string $projectName, ?string $resource = null, string $env = self::DEFAULT_ENV): string
    {
        return match ($this) {
            self::Container => "{$projectName}_{$env}_{$resource}",
            self::Network => "{$projectName}_{$env}_network",
            self::Volume => "{$projectName}_{$env}_{$resource}",
        };
    }

    /**
     * Get volume name with env variable syntax for compose files.
     * Usage: ContainerNamingEnum::Volume->withEnvVar('myapp', 'data') → "myapp_${APP_ENV:-dev}_data"
     */
    public function withEnvVar(string $projectName, string $resource): string
    {
        return match ($this) {
            self::Volume => "{$projectName}_\${APP_ENV:-dev}_{$resource}",
            default => throw new LogicException('withEnvVar() only available for Volume'),
        };
    }

    /**
     * Get volume name without environment (for compose file volume definitions).
     * Usage: ContainerNamingEnum::Volume->volumeKey('myapp', 'postgres_data') → "myapp_postgres_data"
     */
    public function volumeKey(string $projectName, string $resource): string
    {
        return match ($this) {
            self::Volume => "{$projectName}_{$resource}",
            default => throw new LogicException('volumeKey() only available for Volume'),
        };
    }
}
