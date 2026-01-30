<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class TestTutiDirectoryCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:tuti-directory {--clean : Clean up after test}';

    protected $description = 'Test .tuti directory management';

    public function handle(
        ProjectDirectoryService $directoryManager,
        ProjectMetadataService $metadata
    ): int {
        $this->brandedHeader('Directory Management Test');

        $testDir = base_path() . '/tuti-test-' . uniqid();
        mkdir($testDir);

        try {
            // Create new instance for test directory
            $testManager = new ProjectDirectoryService($testDir);
            $testMetadata = new ProjectMetadataService($testManager);

            // Test 1: Check non-existence
            $this->step(1, 8, 'Check .tuti does not exist initially');
            if (! $testManager->exists()) {
                $this->success('PASSED');
            } else {
                $this->failure('FAILED');

                return self::FAILURE;
            }

            // Test 2: Initialize directory structure
            $this->step(2, 8, 'Initialize .tuti directory');
            $testManager->initialize();

            if (is_dir($testManager->getTutiPath())) {
                $this->success('Directory created');
            } else {
                $this->failure('Failed to create directory');

                return self::FAILURE;
            }

            // Test 3: Check required directories
            $this->step(3, 8, 'Validate directory structure');
            $requiredDirs = $testManager->getRequiredDirectories();

            foreach ($requiredDirs as $dir) {
                $path = $testManager->getTutiPath($dir);

                if (is_dir($path)) {
                    $this->created("{$dir}/");
                } else {
                    $this->failure("{$dir}/ - NOT FOUND");

                    return self::FAILURE;
                }
            }
            $this->success('PASSED');

            // Test 4: Create metadata
            $this->step(4, 8, 'Create project metadata');
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
                $this->success('Metadata created');
            } else {
                $this->failure('Failed to create metadata');

                return self::FAILURE;
            }

            // Test 5: Load metadata
            $this->step(5, 8, 'Load and validate metadata');
            $loadedMetadata = $testMetadata->load();

            $this->keyValue('Stack', $loadedMetadata['stack']);
            $this->keyValue('Project', $loadedMetadata['project_name']);
            $this->keyValue('Environment', $loadedMetadata['environments']['current']);
            $this->keyValue('Services', json_encode($loadedMetadata['services']));

            if ($loadedMetadata['stack'] === 'laravel-stack') {
                $this->success('PASSED');
            } else {
                $this->failure('FAILED');

                return self::FAILURE;
            }

            // Test 6: Update metadata
            $this->step(6, 8, 'Update metadata');
            $testMetadata->update([
                'services' => [
                    'databases' => ['postgres'],
                    'cache' => ['redis'],
                    'search' => ['meilisearch'],
                ],
            ]);

            $updatedMetadata = $testMetadata->load();

            if (isset($updatedMetadata['services']['search'])) {
                $this->success('Metadata updated');
            } else {
                $this->failure('Failed to update metadata');

                return self::FAILURE;
            }

            // Test 7: Helper functions
            $this->step(7, 8, 'Test helper functions');
            $tutiPath = tuti_path(null, $testDir);
            $this->keyValue('tuti_path()', $tutiPath);

            $dockerPath = tuti_path('docker', $testDir);
            $this->keyValue("tuti_path('docker')", $dockerPath);

            if (tuti_exists($testDir)) {
                $this->success('Helper functions work');
            } else {
                $this->failure('Helper functions failed');

                return self::FAILURE;
            }

            // Test 8: Accessor methods
            $this->step(8, 8, 'Test metadata accessor methods');
            $this->keyValue('getStack()', $testMetadata->getStack());
            $this->keyValue('getProjectName()', $testMetadata->getProjectName());
            $this->keyValue('getCurrentEnvironment()', $testMetadata->getCurrentEnvironment());

            $this->success('PASSED');

            $this->completed('All tests passed!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failed('Test failed: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            // Cleanup
            if ($this->option('clean') && is_dir($testDir)) {
                $this->action('Cleaning up test directory');
                $testManager = new ProjectDirectoryService($testDir);
                $testManager->clean();
                rmdir($testDir);
                $this->success('Cleaned up');
            } else {
                $this->note("Test directory: {$testDir}");
            }
        }
    }
}
