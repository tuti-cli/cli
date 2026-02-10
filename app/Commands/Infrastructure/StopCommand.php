<?php

declare(strict_types=1);

namespace App\Commands\Infrastructure;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use Exception;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

final class StopCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'infra:stop
                          {--force : Stop without confirmation}';

    protected $description = 'Stop the global infrastructure (Traefik)';

    public function handle(InfrastructureManagerInterface $infrastructureManager): int
    {
        $this->brandedHeader('Stopping Infrastructure');

        if (! $infrastructureManager->isInstalled()) {
            $this->skipped('Infrastructure is not installed');

            return self::SUCCESS;
        }

        if (! $infrastructureManager->isRunning()) {
            $this->skipped('Infrastructure is already stopped');

            return self::SUCCESS;
        }

        // Warn user about consequences
        if (! $this->option('force')) {
            $this->warning('Stopping infrastructure will affect all running tuti projects!');
            $this->newLine();

            if (! confirm('Are you sure you want to stop Traefik?', false)) {
                $this->skipped('Operation cancelled');

                return self::SUCCESS;
            }
        }

        try {
            spin(
                fn () => $infrastructureManager->stop(),
                'Stopping Traefik reverse proxy...'
            );

            $this->success('Infrastructure stopped');
            $this->hint('Run "tuti infra:start" to start it again');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failure('Failed to stop infrastructure: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
