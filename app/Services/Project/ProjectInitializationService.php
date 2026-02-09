<?php

declare(strict_types=1);

namespace App\Services\Project;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final readonly class ProjectInitializationService
{
    public function __construct(
        private ProjectDirectoryService $directoryService,
        private ProjectMetadataService $metadataService
    ) {}

    public function initialize(string $projectName, string $environment): bool
    {
        // 1. Create .tuti directory
        $this->directoryService->create();

        // 2. Clone laravel-stack
        $this->cloneLaravelStack();

        // 3. Move env files to root
        $this->moveEnvFilesToRoot();

        // 4. Create config
        $config = $this->buildMinimalConfig($projectName, $environment);
        $this->metadataService->create($config);

        return true;
    }

    private function cloneLaravelStack(): void
    {
        $tutiPath = $this->directoryService->getTutiPath();

        if (is_dir($tutiPath)) {
            return;
        }

        $process = Process::run([
            'git',
            'clone',
            'https://github.com/tuti-cli/laravel-stack.git',
            '.',
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('Failed to clone laravel-stack:' . $process->errorOutput());
        }
    }

    private function moveEnvFilesToRoot(): void
    {
        $envDir = $this->directoryService->getTutiPath('laravel-stack/environments');
        $projectRoot = $this->directoryService->getProjectRoot();

        if (! is_dir($envDir)) {
            return;
        }

        $envFiles = File::files($envDir);

        foreach ($envFiles as $file) {
            $fileName = $file->getFilename();

            File::copy(
                $file->getPathname(),
                $projectRoot . '/' . $fileName
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMinimalConfig(string $projectName, string $environment): array
    {
        return [
            'project' => [
                'name' => $projectName,
                'type' => 'laravel',
                'version' => '1.0.0',
            ],
            'apps' => [
                [
                    'name' => 'app',
                    'path' => '.',
                    'type' => 'laravel',
                    'ports' => [
                        'http' => 8000,
                    ],
                ],
            ],
            'shared_services' => ['postgres', 'redis'],
            'environments' => [
                'local' => [
                    'type' => 'docker',
                    'host' => "{$projectName}.test",
                    'user' => '{{SYSTEM_USER}}',
                ],
            ],
        ];
    }
}
