<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Stack\StackRegistryManagerService;
use LaravelZero\Framework\Commands\Command;

final class TestRegistryCommand extends Command
{
    protected $signature = 'test:registry';

    protected $description = 'Test service registry functionality';

    public function handle(StackRegistryManagerService $registry): int
    {
        $this->info('🔍 Testing Service Registry...');
        $this->newLine();

        // Test 1: Get version
        $this->info('Registry Version:  ' . $registry->getVersion());
        $this->newLine();

        // Test 2: Get all services
        $this->info('📋 All Available Services:');
        foreach ($registry->getAllServices() as $category => $services) {
            $this->line("  <fg=cyan>{$category}</>:");
            foreach ($services as $name => $config) {
                $this->line("    - {$name}: {$config['description']}");
            }
        }
        $this->newLine();

        // Test 3: Get specific service
        $this->info('🔍 PostgreSQL Service Details:');
        $postgres = $registry->getService('databases', 'postgres');
        $this->line('  Name: ' . $postgres['name']);
        $this->line('  Stub: ' . $postgres['stub']);
        $this->line('  Compatible:  ' . implode(', ', $postgres['compatible_with']));
        $this->newLine();

        // Test 4: Get compatible services for Laravel
        $this->info('🎯 Services Compatible with Laravel: ');
        foreach ($registry->getCompatibleServices('laravel') as $category => $services) {
            $this->line("  {$category}:  " . implode(', ', array_keys($services)));
        }

        $this->newLine();
        $this->info('✅ Service Registry tests completed successfully.');

        return self::SUCCESS;
    }
}
