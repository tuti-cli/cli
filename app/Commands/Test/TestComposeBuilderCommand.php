<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Stack\StackComposeBuilderService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class TestComposeBuilderCommand extends Command
{
    protected $signature = 'test:compose-builder';

    protected $description = 'Test compose builder functionality';

    public function handle(StackComposeBuilderService $builder): int
    {
        $this->info('🔍 Testing ComposeBuilder...');
        $this->newLine();

        // Test configuration
        $selectedServices = [
            'databases.postgres',
            'cache.redis',
        ];

        $projectConfig = [
            'PROJECT_NAME' => 'testapp',
        ];

        $this->info('📋 Building docker-compose for: ');
        $this->line('  Services: ' . implode(', ', $selectedServices));
        $this->line('  Project: ' . $projectConfig['PROJECT_NAME']);
        $this->newLine();

        // Build compose
        try {
            $compose = $builder->build($selectedServices, $projectConfig, 'dev');

            $this->info('✅ Compose structure built successfully! ');
            $this->newLine();

            // Show structure
            $this->info('📦 Services: ');
            foreach (array_keys($compose['services']) as $service) {
                $this->line("  - {$service}");
            }
            $this->newLine();

            $this->info('🗄️  Volumes:');
            foreach (array_keys($compose['volumes']) as $volume) {
                $this->line("  - {$volume}");
            }
            $this->newLine();

            $this->info('🌐 Networks:');
            foreach (array_keys($compose['networks']) as $network) {
                $this->line("  - {$network}");
            }
            $this->newLine();

            $outputPath = app_path('/Commands/Test') . '/docker-compose.test.yml';

            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            $builder->writeToFile($compose, $outputPath);
            $this->info("💾 Compose file written to: {$outputPath}");

            $this->newLine();
            $this->info('✅ All tests passed!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
