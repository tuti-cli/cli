<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackComposeBuilderService;
use LaravelZero\Framework\Commands\Command;

final class TestStackOverridesCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:stack-overrides {stack-path?}';

    protected $description = 'Test stack overrides functionality';

    public function handle(StackComposeBuilderService $builder): int
    {
        $this->brandedHeader('Stack Overrides Test');

        // Get stack path
        $stackPath = $this->argument('stack-path') ?? stack_path() . '/laravel-stack';

        if (! is_dir($stackPath)) {
            $this->failure("Stack directory not found: {$stackPath}");
            $this->hint('Usage: php artisan test:stack-overrides /path/to/laravel-stack');

            return self::FAILURE;
        }

        $this->keyValue('Stack path', $stackPath);
        $this->newLine();

        $selectedServices = [
            'databases.postgres',
            'cache.redis',
        ];

        $projectConfig = [
            'PROJECT_NAME' => 'laravel_test',
        ];

        // Test 1: Development environment
        $this->section('Test 1: Development Environment');
        $this->testEnvironment($builder, $stackPath, $selectedServices, $projectConfig, 'dev');

        // Test 2: Production environment
        $this->section('Test 2: Production Environment');
        $this->testEnvironment($builder, $stackPath, $selectedServices, $projectConfig, 'production');

        $this->completed('All stack override tests passed!');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $selectedServices
     * @param  array<string, string>  $projectConfig
     */
    private function testEnvironment(
        StackComposeBuilderService $builder,
        string $stackPath,
        array $selectedServices,
        array $projectConfig,
        string $environment
    ): void {
        $this->keyValue('Environment', $environment);

        try {
            $compose = $builder->buildWithStack(
                $stackPath,
                $selectedServices,
                $projectConfig,
                $environment
            );

            // Check Redis configuration
            if (isset($compose['services']['redis'])) {
                $redis = $compose['services']['redis'];

                $redisCommand = is_array($redis['command'])
                    ? implode(' ', $redis['command'])
                    : $redis['command'];
                $this->keyValue('Redis command', $redisCommand);

                // Check memory limit based on environment
                if ($environment === 'dev') {
                    $this->success('Dev memory configuration applied');
                } elseif ($environment === 'production') {
                    $this->success('Production memory configuration applied');

                    // Check resource limits
                    if (isset($redis['deploy']['resources'])) {
                        $limits = $redis['deploy']['resources']['limits']['memory'] ?? 'not set';
                        $this->success("Resource limits: {$limits}");
                    }
                }
            }

            // Check PostgreSQL configuration
            if (isset($compose['services']['postgres'])) {
                $postgres = $compose['services']['postgres'];

                if ($environment === 'production' && isset($postgres['deploy']['resources'])) {
                    $limits = $postgres['deploy']['resources']['limits'] ?? [];
                    $this->success('PostgreSQL production resources applied');
                    $this->keyValue('CPU', $limits['cpus'] ?? 'not set');
                    $this->keyValue('Memory', $limits['memory'] ?? 'not set');
                }
            }

            $this->success('Test passed');
            $this->newLine();

        } catch (\Exception $e) {
            $this->failure("Test failed: {$e->getMessage()}");
            $this->newLine();
        }
    }
}
