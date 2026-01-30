<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackRegistryManagerService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class QuickValidateCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'validate:quick';

    protected $description = 'Quick validation check';

    public function handle(StackRegistryManagerService $registry, StackComposeBuilderService $builder): int
    {
        $this->brandedHeader('Quick Validation');

        $checks = [
            'Registry loads' => fn (): true => $registry->getVersion() !== null,
            'PostgreSQL service exists' => fn (): bool => $registry->hasService('databases', 'postgres'),
            'Redis service exists' => fn (): bool => $registry->hasService('cache', 'redis'),
            'MySQL service exists' => fn (): bool => $registry->hasService('databases', 'mysql'),
            'Compose builder works' => fn (): bool => $this->testBuilder($builder),
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

    private function testBuilder(StackComposeBuilderService $builder): bool
    {
        $compose = $builder->build(
            ['databases.postgres'],
            ['PROJECT_NAME' => 'quicktest'],
            'dev'
        );

        return isset($compose['services']['postgres']);
    }
}
