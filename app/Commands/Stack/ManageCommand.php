<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\StackRepositoryService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

final class ManageCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'stack:manage
                          {action? : Action to perform (list, download, update, clear)}
                          {stack? : Stack name for download/update/clear actions}
                          {--all : Apply action to all stacks}';

    protected $description = 'Manage stack templates (download, update, clear cache)';

    public function handle(StackRepositoryService $repositoryService): int
    {
        $this->brandedHeader('Stack Management');

        $action = $this->argument('action') ?? $this->selectAction();

        return match ($action) {
            'list' => $this->listStacks($repositoryService),
            'download' => $this->downloadStack($repositoryService),
            'update' => $this->updateStack($repositoryService),
            'clear' => $this->clearCache($repositoryService),
            default => (function () use ($action): int {
                $this->failure("Unknown action: {$action}");

                return self::FAILURE;
            })(),
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

        if ($stacks === []) {
            $this->warning('No stacks available');

            return self::SUCCESS;
        }

        $this->section('Available Stacks');

        foreach ($stacks as $name => $info) {
            $cached = ($info['cached'] ?? false) ? $this->badgeSuccess('cached') : $this->badgeWarning('not cached');
            $source = $info['source'] ?? 'unknown';

            $this->header($name);
            $this->keyValue('Name', $info['name']);
            $this->keyValue('Description', $info['description']);
            $this->keyValue('Framework', $info['framework']);
            $this->keyValue('Source', "{$source} {$cached}");
        }

        return self::SUCCESS;
    }

    private function downloadStack(StackRepositoryService $repositoryService): int
    {
        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'download');

        if ($stackName === null) {
            $this->failure('No stack specified');

            return self::FAILURE;
        }

        if (! $repositoryService->hasStack($stackName)) {
            $this->failure("Stack not found: {$stackName}");

            return self::FAILURE;
        }

        $path = spin(
            fn (): string => $repositoryService->downloadStack($stackName),
            "Downloading {$stackName} stack..."
        );

        $this->success("Stack downloaded to: {$path}");

        return self::SUCCESS;
    }

    private function updateStack(StackRepositoryService $repositoryService): int
    {
        if ($this->option('all')) {
            return $this->updateAllStacks($repositoryService);
        }

        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'update');

        if ($stackName === null) {
            $this->failure('No stack specified');

            return self::FAILURE;
        }

        $path = spin(
            fn (): string => $repositoryService->updateStack($stackName),
            "Updating {$stackName} stack..."
        );

        $this->success("Stack updated: {$path}");

        return self::SUCCESS;
    }

    private function updateAllStacks(StackRepositoryService $repositoryService): int
    {
        $stacks = $repositoryService->getAvailableStacks();

        foreach ($stacks as $name => $info) {
            if (($info['source'] ?? '') === 'registry') {
                spin(
                    fn (): string => $repositoryService->updateStack($name),
                    "Updating {$name}..."
                );
                $this->success("Updated: {$name}");
            }
        }

        return self::SUCCESS;
    }

    private function clearCache(StackRepositoryService $repositoryService): int
    {
        if ($this->option('all')) {
            if (! confirm('Clear ALL cached stacks?', false)) {
                $this->warning('Cancelled');

                return self::SUCCESS;
            }

            $repositoryService->clearCache();
            $this->success('All stack caches cleared');

            return self::SUCCESS;
        }

        $stackName = $this->argument('stack') ?? $this->selectStack($repositoryService, 'clear cache for');

        if ($stackName === null) {
            $this->failure('No stack specified');

            return self::FAILURE;
        }

        $repositoryService->clearCache($stackName);
        $this->success("Cache cleared for: {$stackName}");

        return self::SUCCESS;
    }

    private function selectStack(StackRepositoryService $repositoryService, string $action): ?string
    {
        $stacks = $repositoryService->getAvailableStacks();

        if ($stacks === []) {
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
