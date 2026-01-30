<?php

declare(strict_types=1);

namespace App\Commands\Test;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackComposeBuilderService;
use Exception;
use LaravelZero\Framework\Commands\Command;

final class TestComposeBuilderCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'test:compose-builder';

    protected $description = 'Test compose builder functionality';

    public function handle(StackComposeBuilderService $builder): int
    {
        $this->brandedHeader('Compose Builder Test');

        // Test configuration
        $selectedServices = [
            'databases.postgres',
            'cache.redis',
        ];

        $projectConfig = [
            'PROJECT_NAME' => 'testapp',
        ];

        $this->section('Build Configuration');
        $this->keyValue('Services', implode(', ', $selectedServices));
        $this->keyValue('Project', $projectConfig['PROJECT_NAME']);

        // Build compose
        try {
            $compose = $builder->build($selectedServices, $projectConfig, 'dev');

            $this->success('Compose structure built successfully');

            // Show structure
            $this->section('Services');
            foreach (array_keys($compose['services']) as $service) {
                $this->bullet($service);
            }

            $this->section('Volumes');
            foreach (array_keys($compose['volumes']) as $volume) {
                $this->bullet($volume);
            }

            $this->section('Networks');
            foreach (array_keys($compose['networks']) as $network) {
                $this->bullet($network);
            }

            $outputPath = app_path('/Commands/Test') . '/docker-compose.test.yml';

            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            $builder->writeToFile($compose, $outputPath);
            $this->created($outputPath);

            $this->completed('All tests passed!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failed('Test failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
