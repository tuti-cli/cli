<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Services\Stack\StackRepositoryService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\confirm;

final class ManageCommand extends Command
{
    protected $signature = 'stack:manage
                          {action? : Action to perform (list, download, update, clear)}
                          {stack? : Stack name for download/update/clear actions}
                          {--all : Apply action to all stacks}';

    protected $description = 'Manage stack templates (download, update, clear cache)';

    public function handle(StackRepositoryService $repositoryService): int
    {
        $action = $this->argument('action') ?? $this->selectAction();

        return match ($action) {
            'list' => $this->listStacks($repositoryService),
            'download' => $this->downloadStack($repositoryService),
            'update' => $this->updateStack($repositoryService),
            'clear' => $this->clearCache($repositoryService),
            default => $this->error("Unknown action: {$action}") ?? self::FAILURE,
        };
    }

    private function selectAction(): string
    {
        return select(
            label: 'What would you like to do?',
            options: [
                'list' => '📋 List available stacks',
                'download' => '⬇️  Download a stack',
                'update' => '🔄 Update a stack',
                'clear' => '🗑️  Clear stack cache',
            ]
        );
    }

    private function listStacks(StackRepositoryService $repositoryService): int
    {
        $stacks = $repositoryService->getAvailableStacks();

        if (empty($stacks)) {
            $this->warn('No stacks available.');

            return self::SUCCESS;
        }

        $this->info('📦 Available Stacks:');
        $this->newLine();

        foreach ($stacks as $name => $info) {
            $cached = ($info['cached'] ?? false) ? '✓ cached' : '○ not cached';
            $source = $info['source'] ?? 'unknown';

            $this->line("  <fg=cyan>{$name}</>");
            $this->line("    Name: {$info['name']}");
            $this->line("    Description: {$info['description']}");
            $this->line("    Framework: {$info['framework']}");
            $this->line("    Source: {$source} ({$cached})");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function downloadStack(StackRepositoryService $repositoryService): int
    {
        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'download');

        if ($stackName === null) {
            $this->error('No stack specified.');

            return self::FAILURE;
        }

        if (! $repositoryService->hasStack($stackName)) {
            $this->error("Stack not found: {$stackName}");

            return self::FAILURE;
        }

        $path = spin(
            fn () => $repositoryService->downloadStack($stackName),
            "Downloading {$stackName} stack..."
        );

        $this->info("✓ Stack downloaded to: {$path}");

        return self::SUCCESS;
    }

    private function updateStack(StackRepositoryService $repositoryService): int
    {
        if ($this->option('all')) {
            return $this->updateAllStacks($repositoryService);
        }

        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'update');

        if ($stackName === null) {
            $this->error('No stack specified.');

            return self::FAILURE;
        }

        $path = spin(
            fn () => $repositoryService->updateStack($stackName),
            "Updating {$stackName} stack..."
        );

        $this->info("✓ Stack updated: {$path}");

        return self::SUCCESS;
    }

    private function updateAllStacks(StackRepositoryService $repositoryService): int
    {
        $stacks = $repositoryService->getAvailableStacks();

        foreach ($stacks as $name => $info) {
            if (($info['source'] ?? '') === 'registry') {
                spin(
                    fn () => $repositoryService->updateStack($name),
                    "Updating {$name}..."
                );
                $this->info("✓ Updated: {$name}");
            }
        }

        return self::SUCCESS;
    }

    private function clearCache(StackRepositoryService $repositoryService): int
    {
        if ($this->option('all')) {
            if (! confirm('Clear ALL cached stacks?', false)) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }

            $repositoryService->clearCache();
            $this->info('✓ All stack caches cleared.');

            return self::SUCCESS;
        }

        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'clear cache for');

        if ($stackName === null) {
            $this->error('No stack specified.');

            return self::FAILURE;
        }

        $repositoryService->clearCache($stackName);
        $this->info("✓ Cache cleared for: {$stackName}");

        return self::SUCCESS;
    }

    private function selectStack(StackRepositoryService $repositoryService, string $action): ?string
    {
        $stacks = $repositoryService->getAvailableStacks();

        if (empty($stacks)) {
            return null;
        }

        $options = [];
        foreach ($stacks as $name => $info) {
            $options[$name] = "{$info['name']} - {$info['description']}";
        }

        return select(
            label: "Select stack to {$action}:",
            options: $options
        );
    }
}
