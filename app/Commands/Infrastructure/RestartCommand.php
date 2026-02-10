<?php

declare(strict_types=1);

namespace App\Commands\Infrastructure;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\spin;

final class RestartCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'infra:restart';

    protected $description = 'Restart the global infrastructure (Traefik)';

    public function handle(InfrastructureManagerInterface $infrastructureManager): int
    {
        $this->brandedHeader('Restarting Infrastructure');

        if (! $infrastructureManager->isInstalled()) {
            $this->failure('Infrastructure is not installed');
            $this->hint('Run "tuti install" first to set up the infrastructure');

            return self::FAILURE;
        }

        try {
            spin(
                function () use ($infrastructureManager): void {
                    $infrastructureManager->stop();
                    $infrastructureManager->start();
                },
                'Restarting Traefik reverse proxy...'
            );

            $this->success('Infrastructure restarted');
            $this->success('Dashboard: https://traefik.local.test');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->failure('Failed to restart infrastructure: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
