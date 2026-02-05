<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackRepositoryService;
use LaravelZero\Framework\Commands\Command;

final class TestRegistryCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:registry {--stack=laravel : Stack to test}';

    protected $description = 'Test service registry functionality';

    public function handle(
        StackRegistryManagerService $registry,
        StackRepositoryService $repositoryService
    ): int {
        $this->brandedHeader('Registry Test');

        $stackName = $this->option('stack');
        $stackPath = $repositoryService->getStackPath($stackName);

        // Load stack-specific registry
        $registry->loadForStack($stackPath);

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

        // Test 3: Get specific service (if exists)
        if ($registry->hasService('databases', 'postgres')) {
            $this->section('PostgreSQL Service Details');
            $postgres = $registry->getService('databases', 'postgres');
            $this->keyValue('Name', $postgres['name']);
            $this->keyValue('Stub', $postgres['stub']);
        } elseif ($registry->hasService('databases', 'mariadb')) {
            $this->section('MariaDB Service Details');
            $mariadb = $registry->getService('databases', 'mariadb');
            $this->keyValue('Name', $mariadb['name']);
            $this->keyValue('Stub', $mariadb['stub']);
        }

        // Test 4: Get all categories
        $this->section('Available Categories');
        $this->keyValue('Categories', implode(', ', $registry->getCategories()));

        $this->completed('Service Registry tests completed successfully');

        return self::SUCCESS;
    }
}
