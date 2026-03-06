<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers\Laravel;

/**
 * Laravel Database Environment Configurator.
 *
 * Handles .env database configuration for Laravel projects running in Docker.
 * Supports PostgreSQL, MySQL, MariaDB, and SQLite databases.
 */
final readonly class LaravelDatabaseConfigurator
{
    /**
     * Database driver mappings from Tuti service keys to Laravel driver names.
     */
    private const array DB_DRIVER_MAP = [
        'databases.postgres' => 'pgsql',
        'databases.mysql' => 'mysql',
        'databases.mariadb' => 'mariadb',
    ];

    /**
     * Default ports for each database driver.
     */
    private const array DEFAULT_PORTS = [
        'pgsql' => '5432',
        'mysql' => '3306',
        'mariadb' => '3306',
    ];

    /**
     * Configure the database connection in .env for Docker.
     */
    public function configureDefaultDatabaseConnection(string $directory, string $database, string $projectName): void
    {
        // Skip if SQLite - Laravel handles it
        if ($database === 'databases.sqlite') {
            $this->commentDatabaseConfigurationForSqlite($directory);

            return;
        }

        $driver = self::DB_DRIVER_MAP[$database] ?? $database;

        // Uncomment database configuration
        $this->uncommentDatabaseConfiguration($directory);

        // Update .env for Docker
        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        // Update DB_CONNECTION
        $this->pregReplaceInFile('/DB_CONNECTION=.*/', "DB_CONNECTION={$driver}", $envPath);
        $this->pregReplaceInFile('/DB_CONNECTION=.*/', "DB_CONNECTION={$driver}", $envExamplePath);

        // Set Docker values
        $this->replaceInFile('DB_HOST=127.0.0.1', 'DB_HOST=db', $envPath);
        $this->replaceInFile('DB_HOST=127.0.0.1', 'DB_HOST=db', $envExamplePath);

        // Set port based on driver
        if (isset(self::DEFAULT_PORTS[$driver])) {
            $this->replaceInFile('DB_PORT=3306', 'DB_PORT=' . self::DEFAULT_PORTS[$driver], $envPath);
            $this->replaceInFile('DB_PORT=3306', 'DB_PORT=' . self::DEFAULT_PORTS[$driver], $envExamplePath);
        }

        // Set database name (convert hyphens to underscores)
        $dbName = str_replace('-', '_', $projectName);
        $this->replaceInFile('DB_DATABASE=laravel', "DB_DATABASE={$dbName}", $envPath);
        $this->replaceInFile('DB_DATABASE=laravel', "DB_DATABASE={$dbName}", $envExamplePath);

        // Set credentials
        $this->replaceInFile('DB_USERNAME=root', 'DB_USERNAME=tuti', $envPath);
        $this->replaceInFile('DB_USERNAME=root', 'DB_USERNAME=tuti', $envExamplePath);

        $this->replaceInFile('DB_PASSWORD=', 'DB_PASSWORD=secret', $envPath);
        $this->replaceInFile('DB_PASSWORD=', 'DB_PASSWORD=secret', $envExamplePath);
    }

    /**
     * Comment out database configuration for SQLite.
     */
    private function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = ['DB_HOST=127.0.0.1', 'DB_PORT=3306', 'DB_DATABASE=laravel', 'DB_USERNAME=root', 'DB_PASSWORD='];

        $commented = array_map(static fn (string $d): string => "# {$d}", $defaults);

        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        if (file_exists($envPath)) {
            $this->replaceInFile($defaults, $commented, $envPath);
        }

        if (file_exists($envExamplePath)) {
            $this->replaceInFile($defaults, $commented, $envExamplePath);
        }
    }

    /**
     * Uncomment database configuration for non-SQLite databases.
     */
    private function uncommentDatabaseConfiguration(string $directory): void
    {
        $commented = ['# DB_HOST=127.0.0.1', '# DB_PORT=3306', '# DB_DATABASE=laravel', '# DB_USERNAME=root', '# DB_PASSWORD='];

        $uncommented = array_map(static fn (string $d): string => mb_substr($d, 2), $commented);

        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        if (file_exists($envPath)) {
            $this->replaceInFile($commented, $uncommented, $envPath);
        }

        if (file_exists($envExamplePath)) {
            $this->replaceInFile($commented, $uncommented, $envExamplePath);
        }
    }

    /**
     * Replace string in file.
     *
     * @param  string|array<int, string>  $search
     * @param  string|array<int, string>  $replace
     */
    private function replaceInFile(string|array $search, string|array $replace, string $file): void
    {
        if (! file_exists($file)) {
            return;
        }

        file_put_contents($file, str_replace($search, $replace, file_get_contents($file)));
    }

    /**
     * Replace using regex in file.
     */
    private function pregReplaceInFile(string $pattern, string $replace, string $file): void
    {
        if (! file_exists($file)) {
            return;
        }

        file_put_contents($file, preg_replace($pattern, $replace, file_get_contents($file)));
    }
}
