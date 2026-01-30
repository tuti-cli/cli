<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackLoaderService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class TestStackLoaderCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:stack-loader {stack-path?}';

    protected $description = 'Test stack loader functionality';

    public function handle(StackLoaderService $loader): int
    {
        $this->brandedHeader('Stack Loader Test');

        // Get stack path (default to laravel-stack if in same parent directory)
        $stackPath = $this->argument('stack-path') ?? stack_path() . '/laravel-stack';

        if (! is_dir($stackPath)) {
            $this->failure("Stack directory not found: {$stackPath}");
            $this->hint('Usage: php artisan test:stack-loader /path/to/laravel-stack');

            return self::FAILURE;
        }

        $this->keyValue('Stack path', $stackPath);
        $this->newLine();

        try {
            // Load manifest
            $manifest = $loader->load($stackPath);
            $this->success('Stack manifest loaded');

            // Validate
            $loader->validate($manifest);
            $this->success('Stack manifest is valid');

            // Show basic info
            $this->section('Stack Information');
            $this->keyValue('Name', $loader->getStackName($manifest));
            $this->keyValue('Type', $loader->getStackType($manifest));
            $this->keyValue('Framework', $loader->getFramework($manifest));
            $this->keyValue('Version', $manifest['version']);

            // Show required services
            $this->section('Required Services');
            foreach ($loader->getRequiredServices($manifest) as $key => $config) {
                $this->header($key);
                $this->keyValue('Category', $config['category']);
                $this->keyValue('Options', implode(', ', $config['options']));
                $this->keyValue('Default', $config['default']);
            }

            // Show optional services
            $this->section('Optional Services');
            foreach ($loader->getOptionalServices($manifest) as $key => $config) {
                $default = $config['default'] ?? 'none';
                $this->header($key);
                $this->keyValue('Category', $config['category']);
                $this->keyValue('Options', implode(', ', $config['options']));
                $this->keyValue('Default', $default);
            }

            // Show default service selection
            $this->section('Default Services');
            foreach ($loader->getDefaultServices($manifest) as $service) {
                $this->bullet($service);
            }

            // Show service overrides
            $this->section('Service Overrides');
            $this->header('cache.redis');
            $overrides = $loader->getServiceOverrides($manifest, 'cache.redis');
            if (isset($overrides['description'])) {
                $this->keyValue('Description', $overrides['description']);
            }
            if (isset($overrides['environments'])) {
                $this->keyValue('Environments', implode(', ', array_keys($overrides['environments'])));
            }

            // Show environment-specific overrides
            $this->header('Production Overrides for cache.redis');
            $loader->getEnvironmentOverrides($manifest, 'cache.redis', 'production');

            $this->completed('All tests passed!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failed('Test failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
