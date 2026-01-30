<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackRegistryManagerService;
use LaravelZero\Framework\Commands\Command;

final class TestRegistryCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:registry';

    protected $description = 'Test service registry functionality';

    public function handle(StackRegistryManagerService $registry): int
    {
        $this->brandedHeader('Registry Test');

        // Test 1: Get version
        $this->labeledValue('Registry Version', $registry->getVersion());
        $this->newLine();

        // Test 2: Get all services
        $this->section('All Available Services');
        foreach ($registry->getAllServices() as $category => $services) {
            $this->header($category);
            foreach ($services as $name => $config) {
                $this->bullet("{$name}: {$config['description']}");
            }
        }

        // Test 3: Get specific service
        $this->section('PostgreSQL Service Details');
        $postgres = $registry->getService('databases', 'postgres');
        $this->keyValue('Name', $postgres['name']);
        $this->keyValue('Stub', $postgres['stub']);
        $this->keyValue('Compatible', implode(', ', $postgres['compatible_with']));

        // Test 4: Get compatible services for Laravel
        $this->section('Services Compatible with Laravel');
        foreach ($registry->getCompatibleServices('laravel') as $category => $services) {
            $this->keyValue($category, implode(', ', array_keys($services)));
        }

        $this->completed('Service Registry tests completed successfully');

        return self::SUCCESS;
    }
}
