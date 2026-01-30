<?php

declare(strict_types=1);

namespace App\Commands\Infrastructure;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;

final class StatusCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'infra:status';

    protected $description = 'Show status of the global infrastructure (Traefik)';

    public function handle(InfrastructureManagerInterface $infrastructureManager): int
    {
        $this->brandedHeader('Infrastructure Status');

        $status = $infrastructureManager->getStatus();

        $this->newLine();

        $this->box('Traefik Reverse Proxy', [
            'Installed' => $status['traefik']['installed'] ? '✅ Yes' : '❌ No',
            'Running' => $status['traefik']['running'] ? '✅ Yes' : '❌ No',
            'Health' => $this->formatHealth($status['traefik']['health']),
            'Path' => $infrastructureManager->getInfrastructurePath() . '/traefik',
        ], 60, true);

        $this->newLine();

        $this->box('Docker Network', [
            'Name' => 'traefik_proxy',
            'Exists' => $status['network']['installed'] ? '✅ Yes' : '❌ No',
        ], 60, true);

        if ($status['traefik']['running']) {
            $this->newLine();
            $this->success('Dashboard: https://traefik.local.test');
        } else {
            $this->newLine();
            $this->hint('Run "tuti infra:start" to start the infrastructure');
        }

        return self::SUCCESS;
    }

    private function formatHealth(string $health): string
    {
        return match ($health) {
            'healthy' => '✅ Healthy',
            'stopped' => '⏸️ Stopped',
            'not_installed' => '❌ Not installed',
            default => "⚠️ {$health}",
        };
    }
}
