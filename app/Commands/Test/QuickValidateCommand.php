<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackRepositoryService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class QuickValidateCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'validate:quick {--stack=laravel : Stack to validate}';

    protected $description = 'Quick validation check';

    public function handle(
        StackRegistryManagerService $registry,
        StackComposeBuilderService $builder,
        StackRepositoryService $repositoryService
    ): int {
        $this->brandedHeader('Quick Validation');

        $stackName = $this->option('stack');
        $stackPath = $repositoryService->getStackPath($stackName);

        // Load stack-specific registry
        $registry->loadForStack($stackPath);

        $checks = [
            'Registry loads' => fn (): true => $registry->getVersion() !== null,
            'Database service exists' => fn (): bool => $registry->hasService('databases', 'postgres') || $registry->hasService('databases', 'mariadb'),
            'Cache service exists' => fn (): bool => $registry->hasService('cache', 'redis'),
            'Compose builder works' => fn (): bool => $this->testBuilder($builder, $registry, $stackPath),
        ];

        $passed = 0;
        $failed = 0;

        $this->section('Running Checks');

        foreach ($checks as $name => $check) {
            try {
                $result = $check();

                if ($result) {
                    $this->success($name);
                    $passed++;
                } else {
                    $this->failure($name);
                    $failed++;
                }
            } catch (Exception $e) {
                $this->failure("{$name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        if ($failed === 0) {
            $this->completed("All {$passed} checks passed!");

            return self::SUCCESS;
        }

        $this->failed("{$failed} check(s) failed");

        return self::FAILURE;
    }

    private function testBuilder(
        StackComposeBuilderService $builder,
        StackRegistryManagerService $registry,
        string $stackPath
    ): bool {
        // Use first available database service
        $dbService = $registry->hasService('databases', 'postgres')
            ? 'databases.postgres'
            : 'databases.mariadb';

        $compose = $builder->buildWithStack(
            $stackPath,
            [$dbService],
            ['PROJECT_NAME' => 'quicktest'],
            'dev'
        );

        return isset($compose['services']);
    }
}
