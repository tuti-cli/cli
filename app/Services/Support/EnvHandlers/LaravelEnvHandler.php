<?php

declare(strict_types=1);

namespace App\Services\Support\EnvHandlers;

use App\Services\Support\EnvFileService;

/**
 * Handles .env file operations for Laravel projects.
 */
final readonly class LaravelEnvHandler
{
    public function __construct(
        private EnvFileService $envService,
    ) {}

    /**
     * Detect if this is a Laravel project.
     */
    public function detect(string $directory): bool
    {
        return file_exists(rtrim($directory, '/') . '/artisan');
    }

    /**
     * Configure .env for Laravel Docker environment.
     *
     * @param  array<string, mixed>  $options
     */
    public function configure(string $directory, string $projectName, array $options = []): bool
    {
        // Laravel creates .env automatically via composer create-project
        // Just update values and append Tuti section

        if (! $this->envService->exists($directory)) {
            return false;
        }

        // Update Docker service variables
        $appDomain = $projectName . '.local.test';
        $hasRedis = $options['has_redis'] ?? false;
        $database = $options['database'] ?? null;

        $replacements = $this->getBaseReplacements($appDomain);

        // Add database-specific replacements based on selection
        $replacements = array_merge($replacements, $this->getDatabaseReplacements($database));

        if ($hasRedis) {
            $replacements = array_merge($replacements, $this->getRedisReplacements());
        }

        $this->envService->updateValues($directory, $replacements);

        // Append Tuti section
        return $this->envService->appendTutiSection($directory, $projectName, [
            'stack_type' => 'laravel',
            'php_version' => $options['php_version'] ?? '8.4',
        ]);
    }

    /**
     * Get database-specific replacements based on selected database.
     *
     * @return array<string, string>
     */
    private function getDatabaseReplacements(?string $database): array
    {
        return match ($database) {
            'postgres' => [
                '/^[\s]*#?[\s]*DB_CONNECTION=.*$/m' => 'DB_CONNECTION=pgsql',
                '/^[\s]*#?[\s]*DB_HOST=.*$/m' => 'DB_HOST=postgres',
                '/^[\s]*#?[\s]*DB_PORT=.*$/m' => 'DB_PORT=5432',
                '/^[\s]*#?[\s]*DB_DATABASE=.*$/m' => 'DB_DATABASE=laravel',
                '/^[\s]*#?[\s]*DB_USERNAME=.*$/m' => 'DB_USERNAME=laravel',
                '/^[\s]*#?[\s]*DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',
            ],
            'mysql' => [
                '/^[\s]*#?[\s]*DB_CONNECTION=.*$/m' => 'DB_CONNECTION=mysql',
                '/^[\s]*#?[\s]*DB_HOST=.*$/m' => 'DB_HOST=mysql',
                '/^[\s]*#?[\s]*DB_PORT=.*$/m' => 'DB_PORT=3306',
                '/^[\s]*#?[\s]*DB_DATABASE=.*$/m' => 'DB_DATABASE=laravel',
                '/^[\s]*#?[\s]*DB_USERNAME=.*$/m' => 'DB_USERNAME=laravel',
                '/^[\s]*#?[\s]*DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',
            ],
            'mariadb' => [
                '/^[\s]*#?[\s]*DB_CONNECTION=.*$/m' => 'DB_CONNECTION=mysql',
                '/^[\s]*#?[\s]*DB_HOST=.*$/m' => 'DB_HOST=mariadb',
                '/^[\s]*#?[\s]*DB_PORT=.*$/m' => 'DB_PORT=3306',
                '/^[\s]*#?[\s]*DB_DATABASE=.*$/m' => 'DB_DATABASE=laravel',
                '/^[\s]*#?[\s]*DB_USERNAME=.*$/m' => 'DB_USERNAME=laravel',
                '/^[\s]*#?[\s]*DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',
            ],
            default => [
                // No database selected - use SQLite (Laravel default)
                '/^[\s]*#?[\s]*DB_CONNECTION=.*$/m' => 'DB_CONNECTION=sqlite',
                '/^[\s]*#?[\s]*DB_HOST=.*$/m' => '# DB_HOST=127.0.0.1',
                '/^[\s]*#?[\s]*DB_PORT=.*$/m' => '# DB_PORT=3306',
                '/^[\s]*#?[\s]*DB_DATABASE=.*$/m' => '# DB_DATABASE=laravel',
                '/^[\s]*#?[\s]*DB_USERNAME=.*$/m' => '# DB_USERNAME=root',
                '/^[\s]*#?[\s]*DB_PASSWORD=.*$/m' => '# DB_PASSWORD=',
            ],
        };
    }

    /**
     * Get base replacements for Laravel Docker environment.
     *
     * @return array<string, string>
     */
    private function getBaseReplacements(string $appDomain): array
    {
        return [
            // App URL
            '/^[\s]*#?[\s]*APP_URL=.*$/m' => "APP_URL=https://{$appDomain}",

            // Mail (Mailpit)
            '/^[\s]*#?[\s]*MAIL_HOST=.*$/m' => 'MAIL_HOST=mailpit',
            '/^[\s]*#?[\s]*MAIL_PORT=.*$/m' => 'MAIL_PORT=1025',
            '/^[\s]*#?[\s]*MAIL_MAILER=.*$/m' => 'MAIL_MAILER=smtp',

            // Redis
            '/^[\s]*#?[\s]*REDIS_HOST=.*$/m' => 'REDIS_HOST=redis',
            '/^[\s]*#?[\s]*REDIS_PASSWORD=.*$/m' => 'REDIS_PASSWORD=',
        ];
    }

    /**
     * Get Redis-specific replacements.
     *
     * @return array<string, string>
     */
    private function getRedisReplacements(): array
    {
        return [
            '/^[\s]*#?[\s]*CACHE_STORE=.*$/m' => 'CACHE_STORE=redis',
            '/^[\s]*#?[\s]*SESSION_DRIVER=.*$/m' => 'SESSION_DRIVER=redis',
            '/^[\s]*#?[\s]*QUEUE_CONNECTION=.*$/m' => 'QUEUE_CONNECTION=redis',
        ];
    }
}
