<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Stack\ServiceStackLoader;
use LaravelZero\Framework\Commands\Command;

final class TestStackLoaderCommand extends Command
{
    protected $signature = 'test:stack-loader {stack-path? }';

    protected $description = 'Test stack loader functionality';

    public function handle(ServiceStackLoader $loader): int
    {
        $this->info('🔍 Testing StackLoader.. .');
        $this->newLine();

        // Get stack path (default to laravel-stack if in same parent directory)
        $stackPath = $this->argument('stack-path') ?? stack_path() . '/laravel-stack';

        if (! is_dir($stackPath)) {
            $this->error("Stack directory not found: {$stackPath}");
            $this->line('Usage: php artisan test:stack-loader /path/to/laravel-stack');

            return self::FAILURE;
        }

        $this->line("Stack path: {$stackPath}");
        $this->newLine();

        try {
            // Load manifest
            $manifest = $loader->load($stackPath);

            $this->info('✅ Stack manifest loaded fully! ');
            $this->newLine();

            // Validate
            $loader->validate($manifest);
            $this->info('✅ Stack manifest is valid!');
            $this->newLine();

            // Show basic info
            $this->info('📋 Stack Information:');
            $this->line('  Name: ' . $loader->getStackName($manifest));
            $this->line('  Type: ' . $loader->getStackType($manifest));
            $this->line('  Framework: ' .  $loader->getFramework($manifest));
            $this->line('  Version: ' . $manifest['version']);
            $this->newLine();

            // Show required services
            $this->info('🔧 Required Services:');
            foreach ($loader->getRequiredServices($manifest) as $key => $config) {
                $this->line("  {$key}:");
                $this->line("    Category: {$config['category']}");
                $this->line("    Options: " . implode(', ', $config['options']));
                $this->line("    Default:  {$config['default']}");
            }
            $this->newLine();

            // Show optional services
            $this->info('⚙️  Optional Services:');
            foreach ($loader->getOptionalServices($manifest) as $key => $config) {
                $default = $config['default'] ?? 'none';
                $this->line("  {$key}:");
                $this->line("    Category:  {$config['category']}");
                $this->line("    Options: " . implode(', ', $config['options']));
                $this->line("    Default:  {$default}");
            }
            $this->newLine();

            // Show default service selection
            $this->info('📦 Default Services:');
            foreach ($loader->getDefaultServices($manifest) as $service) {
                $this->line("  - {$service}");
            }
            $this->newLine();

            // Show service overrides
            $this->info('🎨 Service Overrides:');
            $this->line('  cache.redis:');
            $overrides = $loader->getServiceOverrides($manifest, 'cache.redis');
            if (isset($overrides['description'])) {
                $this->line("    Description: {$overrides['description']}");
            }
            if (isset($overrides['environments'])) {
                $this->line('    Environments: ' . implode(', ', array_keys($overrides['environments'])));
            }
            $this->newLine();

            // Show environment-specific overrides
            $this->info('🌍 Production Overrides for cache.redis:');
            $envOverrides = $loader->getEnvironmentOverrides($manifest, 'cache.redis', 'production');
            dump($envOverrides);

            $this->newLine();
            $this->info('✅ All tests passed! ');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' .  $e->getMessage());

            return self::FAILURE;
        }
    }
}
