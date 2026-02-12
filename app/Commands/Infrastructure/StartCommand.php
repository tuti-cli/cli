<?php

declare(strict_types=1);

namespace App\Commands\Infrastructure;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use Exception;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\spin;

final class StartCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'infra:start';

    protected $description = 'Start the global infrastructure (Traefik)';

    public function handle(InfrastructureManagerInterface $infrastructureManager): int
    {
        $this->brandedHeader('Starting Infrastructure');

        if (! $infrastructureManager->isInstalled()) {
            $this->failure('Infrastructure is not installed');
            $this->hint('Run "tuti install" first to set up the infrastructure');

            return self::FAILURE;
        }

        if ($infrastructureManager->isRunning()) {
            $this->newLine();
            $this->skipped('Infrastructure is already running');
            $this->success('Dashboard: https://traefik.local.test');

            return self::SUCCESS;
        }

        try {
            spin(
                fn () => $infrastructureManager->start(),
                'Starting Traefik reverse proxy...'
            );

            $this->newLine();

            $this->success('Infrastructure started');
            $this->success('Dashboard: https://traefik.local.test');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failure('Failed to start infrastructure: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
