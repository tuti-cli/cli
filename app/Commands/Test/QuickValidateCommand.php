<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Stack\ServiceComposeStackBuilder;
use App\Services\Stack\ServiceRegistryJsonReader;
use LaravelZero\Framework\Commands\Command;

final class QuickValidateCommand extends Command
{
    protected $signature = 'validate:quick';

    protected $description = 'Quick validation check';

    public function handle(ServiceRegistryJsonReader $registry, ServiceComposeStackBuilder $builder): int
    {
        $this->info('🚀 Quick Validation Check');
        $this->newLine();

        $checks = [
            'Registry loads' => fn () => $registry->getVersion() !== null,
            'PostgreSQL service exists' => fn () => $registry->hasService('databases', 'postgres'),
            'Redis service exists' => fn () => $registry->hasService('cache', 'redis'),
            'MySQL service exists' => fn () => $registry->hasService('databases', 'mysql'),
            'Compose builder works' => fn () => $this->testBuilder($builder),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $name => $check) {
            try {
                $result = $check();

                if ($result) {
                    $this->components->info("{$name}");
                    $passed++;
                } else {
                    $this->components->error("{$name}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->components->error("{$name}:  {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        if ($failed === 0) {
            $this->components->info("✅ All {$passed} checks passed!");

            return self::SUCCESS;
        }

        $this->components->error("⚠️  {$failed} check(s) failed!");

        return self::FAILURE;
    }

    private function testBuilder(ServiceComposeStackBuilder $builder): bool
    {
        $compose = $builder->build(
            ['databases.postgres'],
            ['PROJECT_NAME' => 'quicktest'],
            'dev'
        );

        return isset($compose['services']['postgres']);
    }
}
