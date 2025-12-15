<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;

/**
 * Class FakeDockerOrchestrator
 *
 * Mock implementation of OrchestratorInterface for testing without Docker.
 * Tracks method calls and allows configuring responses.
 */
final class FakeDockerOrchestrator implements OrchestratorInterface
{
    public bool $startCalled = false;

    public bool $stopCalled = false;

    public bool $restartCalled = false;

    public bool $statusCalled = false;

    public bool $logsCalled = false;

    public bool $startResult = true;

    public bool $stopResult = true;

    public bool $restartResult = true;

    /** @var array<int, array<string, mixed>> */
    public array $statusResponse = [];

    public ? Project $lastProject = null;

    public ? string $lastService = null;

    public ? bool $lastFollow = null;

    public function start(Project $project): bool
    {
        $this->startCalled = true;
        $this->lastProject = $project;

        return $this->startResult;
    }

    public function stop(Project $project): bool
    {
        $this->stopCalled = true;
        $this->lastProject = $project;

        return $this->stopResult;
    }

    public function restart(Project $project, ? string $service = null): bool
    {
        $this->restartCalled = true;
        $this->lastProject = $project;
        $this->lastService = $service;

        return $this->restartResult;
    }

    public function status(Project $project): array
    {
        $this->statusCalled = true;
        $this->lastProject = $project;

        return $this->statusResponse;
    }

    public function logs(Project $project, ?string $service = null, bool $follow = false): void
    {
        $this->logsCalled = true;
        $this->lastProject = $project;
        $this->lastService = $service;
        $this->lastFollow = $follow;

        // Simulate log output
        if ($service !== null) {
            echo "[{$service}] Test log line 1\n";
            echo "[{$service}] Test log line 2\n";
        } else {
            echo "[app] Application log\n";
            echo "[nginx] Nginx log\n";
        }
    }

    public function reset(): void
    {
        $this->startCalled = false;
        $this->stopCalled = false;
        $this->restartCalled = false;
        $this->statusCalled = false;
        $this->logsCalled = false;
        $this->lastProject = null;
        $this->lastService = null;
        $this->lastFollow = null;
        $this->startResult = true;
        $this->stopResult = true;
        $this->restartResult = true;
        $this->statusResponse = [];
    }
}
