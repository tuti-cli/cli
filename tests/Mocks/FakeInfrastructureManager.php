<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\InfrastructureManagerInterface;
use RuntimeException;

/**
 * Class FakeInfrastructureManager
 *
 * Mock implementation of InfrastructureManagerInterface for testing without Docker.
 * Tracks method calls and allows configuring responses.
 */
final class FakeInfrastructureManager implements InfrastructureManagerInterface
{
    public bool $installCalled = false;

    public bool $startCalled = false;

    public bool $stopCalled = false;

    public bool $ensureReadyCalled = false;

    public bool $isInstalledResult = false;

    public bool $isRunningResult = false;

    public bool $installResult = true;

    public bool $startResult = true;

    public bool $stopResult = true;

    public bool $ensureReadyResult = true;

    public array $statusResponse = [];

    public array $installedServices = [];

    public array $runningServices = [];

    public int $installCallCount = 0;

    public int $startCallCount = 0;

    public function __construct()
    {
        $this->setDefaultStatus();
    }

    public function isInstalled(): bool
    {
        return $this->isInstalledResult;
    }

    public function isRunning(): bool
    {
        return $this->isRunningResult;
    }

    public function install(): void
    {
        $this->installCalled = true;
        $this->installCallCount++;

        if (! $this->installResult) {
            throw new RuntimeException('Failed to install infrastructure');
        }

        $this->isInstalledResult = true;
        $this->installedServices[] = 'traefik';
        $this->updateStatus();
    }

    public function start(): void
    {
        $this->startCalled = true;
        $this->startCallCount++;

        if (! $this->startResult) {
            throw new RuntimeException('Failed to start infrastructure');
        }

        if (! $this->isInstalledResult) {
            throw new RuntimeException('Infrastructure is not installed');
        }

        $this->isRunningResult = true;
        $this->runningServices[] = 'traefik';
        $this->updateStatus();
    }

    public function stop(): void
    {
        $this->stopCalled = true;

        if (! $this->stopResult) {
            throw new RuntimeException('Failed to stop infrastructure');
        }

        $this->isRunningResult = false;
        $this->runningServices = [];
        $this->updateStatus();
    }

    public function ensureReady(): bool
    {
        $this->ensureReadyCalled = true;

        if (! $this->ensureReadyResult) {
            throw new RuntimeException('Failed to ensure infrastructure is ready');
        }

        return $this->ensureReadyResult;
    }

    public function getStatus(): array
    {
        return $this->statusResponse;
    }

    public function getInfrastructurePath(): string
    {
        return '/fake/.tuti/infrastructure';
    }

    public function getDashboardUrl(): string
    {
        return 'https://traefik.local.test';
    }

    public function restart(): void
    {
        $this->stop();
        $this->start();
    }

    public function ensureNetworkExists(string $networkName = 'traefik_proxy'): bool
    {
        return $this->ensureReadyResult;
    }

    /**
     * Set that infrastructure is installed.
     */
    public function setInstalled(bool $installed = true): void
    {
        $this->isInstalledResult = $installed;
        if ($installed) {
            $this->installedServices[] = 'traefik';
        }
        $this->updateStatus();
    }

    /**
     * Set that infrastructure is running.
     */
    public function setRunning(bool $running = true): void
    {
        $this->isRunningResult = $running;
        if ($running) {
            $this->runningServices[] = 'traefik';
        } else {
            $this->runningServices = [];
        }
        $this->updateStatus();
    }

    /**
     * Set the result of install operation.
     */
    public function setInstallResult(bool $result): void
    {
        $this->installResult = $result;
    }

    /**
     * Set the result of start operation.
     */
    public function setStartResult(bool $result): void
    {
        $this->startResult = $result;
    }

    /**
     * Set the result of stop operation.
     */
    public function setStopResult(bool $result): void
    {
        $this->stopResult = $result;
    }

    /**
     * Reset all tracking data to initial state.
     */
    public function reset(): void
    {
        $this->installCalled = false;
        $this->startCalled = false;
        $this->stopCalled = false;
        $this->ensureReadyCalled = false;
        $this->isInstalledResult = false;
        $this->isRunningResult = false;
        $this->installResult = true;
        $this->startResult = true;
        $this->stopResult = true;
        $this->ensureReadyResult = true;
        $this->installedServices = [];
        $this->runningServices = [];
        $this->installCallCount = 0;
        $this->startCallCount = 0;
        $this->setDefaultStatus();
    }

    private function setDefaultStatus(): void
    {
        $this->statusResponse = [
            'traefik' => [
                'installed' => $this->isInstalledResult,
                'running' => $this->isRunningResult,
                'health' => $this->isRunningResult ? 'healthy' : ($this->isInstalledResult ? 'stopped' : 'not_installed'),
            ],
            'network' => [
                'installed' => $this->isInstalledResult,
                'health' => 'healthy',
            ],
        ];
    }

    private function updateStatus(): void
    {
        $this->setDefaultStatus();
    }
}
