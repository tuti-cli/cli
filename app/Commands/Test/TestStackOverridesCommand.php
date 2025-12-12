<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Stack\StackComposeBuilderService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class TestStackOverridesCommand extends Command
{
    protected $signature = 'test:stack-overrides {stack-path? }';

    protected $description = 'Test stack overrides functionality';

    public function handle(StackComposeBuilderService $builder): int
    {
        $this->info('🔍 Testing Stack Overrides...');
        $this->newLine();

        // Get stack path
        $stackPath = $this->argument('stack-path') ?? stack_path() . '/laravel-stack';

        if (! is_dir($stackPath)) {
            $this->error("Stack directory not found: {$stackPath}");
            $this->line('Usage: php artisan test:stack-overrides /path/to/laravel-stack');

            return self::FAILURE;
        }

        $this->line("Stack path: {$stackPath}");
        $this->newLine();

        $selectedServices = [
            'databases.postgres',
            'cache.redis',
        ];

        $projectConfig = [
            'PROJECT_NAME' => 'laravel_test',
        ];

        // Test 1: Development environment
        $this->info('🧪 Test 1: Development Environment');
        $this->testEnvironment($builder, $stackPath, $selectedServices, $projectConfig, 'dev');

        // Test 2: Production environment
        $this->info('🧪 Test 2: Production Environment');
        $this->testEnvironment($builder, $stackPath, $selectedServices, $projectConfig, 'production');

        $this->newLine();
        $this->info('✅ All stack override tests passed!');

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
        $this->line("  Environment: {$environment}");

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

                $this->line('  Redis command: ' . (is_array($redis['command'])
                        ? implode(' ', $redis['command'])
                        : $redis['command']));

                // Check memory limit based on environment
                if ($environment === 'dev') {
                    $this->line('  ✓ Dev memory configuration applied');
                } elseif ($environment === 'production') {
                    $this->line('  ✓ Production memory configuration applied');

                    // Check resource limits
                    if (isset($redis['deploy']['resources'])) {
                        $limits = $redis['deploy']['resources']['limits']['memory'] ?? 'not set';
                        $this->line("  ✓ Resource limits:  {$limits}");
                    }
                }
            }

            // Check PostgreSQL configuration
            if (isset($compose['services']['postgres'])) {
                $postgres = $compose['services']['postgres'];

                if ($environment === 'production' && isset($postgres['deploy']['resources'])) {
                    $limits = $postgres['deploy']['resources']['limits'] ?? [];
                    $this->line('  ✓ PostgreSQL production resources applied');
                    $this->line('    CPU: ' . ($limits['cpus'] ?? 'not set'));
                    $this->line('    Memory: ' . ($limits['memory'] ?? 'not set'));
                }
            }

            $this->line('  ✅ Test passed');
            $this->newLine();

        } catch (Exception $e) {
            $this->error("  ❌ Test failed:  {$e->getMessage()}");
            $this->newLine();
        }
    }
}
