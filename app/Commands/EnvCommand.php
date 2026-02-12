<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use App\Services\Project\ProjectDirectoryService;
use LaravelZero\Framework\Commands\Command;

final class EnvCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'env:check
                          {--show : Show environment variables (excluding sensitive)}';

    protected $description = 'Check environment configuration';

    public function handle(ProjectDirectoryService $dirService): int
    {
        $this->brandedHeader('Environment Configuration');

        // Check if in a project
        if (! $dirService->exists()) {
            $this->failure('Not in a tuti project directory');
            $this->hint('Run this command from a project root or run "tuti stack:laravel" to create a project');

            return self::FAILURE;
        }

        $projectRoot = $dirService->getProjectRoot();
        $envFile = $projectRoot . '/.env';

        // Check if .env exists
        if (! file_exists($envFile)) {
            $this->failure('.env file not found in project root');
            $this->hint('Expected location: ' . $envFile);
            $this->hint('Run "tuti stack:laravel --force" to reinitialize');

            return self::FAILURE;
        }

        $this->success('.env file found: ' . $envFile);
        $this->newLine();

        // Read and parse .env
        $content = file_get_contents($envFile);
        $lines = explode("\n", $content);

        // Check for required Laravel variables
        $this->section('Laravel Configuration');
        $laravelVars = [
            'APP_NAME' => 'Application Name',
            'APP_KEY' => 'Encryption Key',
            'APP_ENV' => 'Environment',
            'APP_URL' => 'Application URL',
            'DB_CONNECTION' => 'Database Driver',
            'DB_HOST' => 'Database Host',
            'DB_DATABASE' => 'Database Name',
            'REDIS_HOST' => 'Redis Host',
        ];

        $this->checkVariables($lines, $laravelVars);

        // Check for tuti-specific variables
        $this->newLine();
        $this->section('Docker Configuration');

        $hasTutiSection = str_contains($content, 'TUTI-CLI DOCKER CONFIGURATION');

        if (! $hasTutiSection) {
            $this->warning('⚠️  tuti-cli section not found in .env');
            $this->hint('Run "tuti stack:laravel --force" to add Docker configuration');
            $this->newLine();
        }

        $tutiVars = [
            'PROJECT_NAME' => 'Project Name',
            'APP_DOMAIN' => 'Application Domain',
            'PHP_VERSION' => 'PHP Version',
            'BUILD_TARGET' => 'Build Target',
        ];

        $this->checkVariables($lines, $tutiVars);

        // Show full env if requested
        if ($this->option('show')) {
            $this->newLine();
            $this->section('Environment Variables (sensitive values hidden)');
            $this->showEnvVariables($lines);
        }

        $this->newLine();
        $this->info('💡 Tip: Edit .env to configure your project');
        $this->info('   After changes, restart: tuti local:stop && tuti local:start');

        return self::SUCCESS;
    }

    /**
     * Check if variables exist in .env.
     *
     * @param  array<int, string>  $lines
     * @param  array<string, string>  $variables
     */
    private function checkVariables(array $lines, array $variables): void
    {
        foreach (array_keys($variables) as $var) {
            $found = false;
            $value = null;

            foreach ($lines as $line) {
                $line = mb_trim($line);
                if (str_starts_with($line, $var . '=')) {
                    $found = true;
                    $value = mb_substr($line, mb_strlen($var) + 1);
                    break;
                }
            }

            if ($found) {
                $displayValue = $this->maskSensitiveValue($var, $value);
                $this->line("  ✅ {$var} = <fg=cyan>{$displayValue}</>");
            } else {
                $this->line("  ❌ {$var} <fg=red>(missing)</>");
            }
        }
    }

    /**
     * Mask sensitive values.
     */
    private function maskSensitiveValue(string $key, ?string $value): string
    {
        if (in_array($value, [null, '', 'null'], true)) {
            return '<not set>';
        }

        $sensitiveKeys = ['PASSWORD', 'KEY', 'SECRET', 'TOKEN'];

        foreach ($sensitiveKeys as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return str_repeat('*', min(mb_strlen($value), 20));
            }
        }

        return $value;
    }

    /**
     * Show all environment variables with sensitive values masked.
     *
     * @param  array<int, string>  $lines
     */
    private function showEnvVariables(array $lines): void
    {
        foreach ($lines as $line) {
            $line = mb_trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                if (str_starts_with($line, '#')) {
                    $this->line("<fg=gray>{$line}</>");
                }

                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $displayValue = $this->maskSensitiveValue($key, $value);
                $this->line("  {$key}=<fg=cyan>{$displayValue}</>");
            }
        }
    }
}
