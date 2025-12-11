<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Services\Tuti\ServiceTutiDirectoryManager;
use App\Services\Tuti\ServiceTutiJsonMetadataManager;
use LaravelZero\Framework\Commands\Command;

final class TestTutiDirectoryCommand extends Command
{
    protected $signature = 'test:tuti-directory {--clean :  Clean up after test}';

    protected $description = 'Test . tuti directory management';

    public function handle(
        ServiceTutiDirectoryManager $directoryManager,
        ServiceTutiJsonMetadataManager $metadata
    ): int {
        $this->info('🔍 Testing .tuti Directory Management.. .');
        $this->newLine();

        $testDir = base_path() . '/tuti-test-' . uniqid();
        mkdir($testDir);

        try {
            // Create new instance for test directory
            $testManager = new ServiceTutiDirectoryManager($testDir);
            $testMetadata = new ServiceTutiJsonMetadataManager($testManager);

            // Test 1: Check non-existence
            $this->info('Test 1: Check . tuti does not exist initially');
            if (!  $testManager->exists()) {
                $this->components->info('✓ PASSED');
            } else {
                $this->components->error('✗ FAILED');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 2: Initialize directory structure
            $this->info('Test 2: Initialize .tuti directory');
            $testManager->initialize();

            if (is_dir($testManager->getTutiPath())) {
                $this->components->info('✓ Directory created');
            } else {
                $this->components->error('✗ Failed to create directory');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 3: Check required directories
            $this->info('Test 3: Validate directory structure');
            $requiredDirs = $testManager->getRequiredDirectories();

            foreach ($requiredDirs as $dir) {
                $path = $testManager->getTutiPath($dir);

                if (!in_array($dir, $requiredDirs)) {
                    $this->error("  Validation error: Unexpected directory {$dir}/ found.");
                }

                if (is_dir($path)) {
                    $this->line("  ✓ {$dir}/");
                } else {
                    $this->components->error("  ✗ {$dir}/ - NOT FOUND");

                    return self::FAILURE;
                }
            }
            $this->components->info('✓ PASSED');
            $this->newLine();

            // Test 4: Create metadata
            $this->info('Test 4: Create project metadata');
            $testMetadata->create([
                'stack' => 'laravel-stack',
                'stack_version' => '1.0.0',
                'project_name' => 'test-project',
                'environment' => 'dev',
                'services' => [
                    'databases' => ['postgres'],
                    'cache' => ['redis'],
                ],
                'features' => [
                    'traefik' => true,
                ],
            ]);

            if ($testMetadata->exists()) {
                $this->components->info('✓ Metadata created');
            } else {
                $this->components->error('✗ Failed to create metadata');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 5: Load metadata
            $this->info('Test 5: Load and validate metadata');
            $loadedMetadata = $testMetadata->load();

            $this->line('  Stack: ' . $loadedMetadata['stack']);
            $this->line('  Project: ' . $loadedMetadata['project_name']);
            $this->line('  Environment: ' . $loadedMetadata['environments']['current']);
            $this->line('  Services: ' . json_encode($loadedMetadata['services']));

            if ($loadedMetadata['stack'] === 'laravel-stack') {
                $this->components->info('✓ PASSED');
            } else {
                $this->components->error('✗ FAILED');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 6: Update metadata
            $this->info('Test 6: Update metadata');
            $testMetadata->update([
                'services' => [
                    'databases' => ['postgres'],
                    'cache' => ['redis'],
                    'search' => ['meilisearch'],
                ],
            ]);

            $updatedMetadata = $testMetadata->load();

            if (isset($updatedMetadata['services']['search'])) {
                $this->components->info('✓ Metadata updated');
            } else {
                $this->components->error('✗ Failed to update metadata');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 7: Helper functions
            $this->info('Test 7: Test helper functions');
            $tutiPath = tuti_path(null, $testDir);
            $this->line("  tuti_path(): {$tutiPath}");

            $dockerPath = tuti_path('docker', $testDir);
            $this->line("  tuti_path('docker'): {$dockerPath}");

            if (is_tuti_exists($testDir)) {
                $this->components->info('✓ Helper functions work');
            } else {
                $this->components->error('✗ Helper functions failed');

                return self::FAILURE;
            }
            $this->newLine();

            // Test 8: Accessor methods
            $this->info('Test 8: Test metadata accessor methods');
            $this->line('  getStack(): ' . $testMetadata->getStack());
            $this->line('  getProjectName(): ' . $testMetadata->getProjectName());
            $this->line('  getCurrentEnvironment(): ' . $testMetadata->getCurrentEnvironment());

            $this->components->info('✓ PASSED');
            $this->newLine();

            $this->info('✅ All tests passed! ');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' .  $e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        } finally {
            // Cleanup
            if ($this->option('clean') && is_dir($testDir)) {
                $this->info('🧹 Cleaning up test directory...');
                $testManager = new ServiceTutiDirectoryManager($testDir);
                $testManager->clean();
                rmdir($testDir);
                $this->line('✓ Cleaned up');
            } else {
                $this->line("Test directory:  {$testDir}");
            }
        }
    }
}
