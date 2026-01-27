<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Contracts\OrchestratorInterface;
use App\Contracts\StateManagerInterface;
use App\Domain\Project\Enums\ProjectStateEnum;
use App\Domain\Project\Project;
use RuntimeException;
use Throwable;

/**
 * Class StateManagerService
 *
 * This service is the "brain" for project lifecycle changes.
 * It coordinates:
 * 1. Validation: Can we transition from A to B?
 * 2. Execution: Calling the orchestrator (Docker) to do the work.
 * 3. Persistence: Updating the project's state in memory or storage.
 *
 * Why this matters:
 * It centralizes the logic for "How do I start a project?". Instead of multiple
 * commands knowing how to verify state and run docker, they just ask this service.
 */
final readonly class ProjectStateManagerService implements StateManagerInterface
{
    public function __construct(
        private OrchestratorInterface $orchestrator
    ) {}

    /**
     * Transition a project to the STARTING/RUNNING state.
     */
    public function start(Project $project): void
    {
        // 1. Validate State
        if ($project->getState() === ProjectStateEnum::RUNNING) {
            throw new RuntimeException("Project {$project->getName()} is already running.");
        }

        // 2. Update State (Transient)
        $project->setState(ProjectStateEnum::STARTING);

        // 3. Execute Infrastructure Change
        try {
            $success = $this->orchestrator->start($project);

            if ($success) {
                $project->setState(ProjectStateEnum::RUNNING);
            } else {
                $project->setState(ProjectStateEnum::ERROR);
                throw new RuntimeException('Failed to start project containers.');
            }
        } catch (Throwable $e) {
            $project->setState(ProjectStateEnum::ERROR);
            throw $e;
        }
    }

    /**
     * Transition a project to the STOPPED/READY state.
     */
    public function stop(Project $project): void
    {
        // 1. Validate State
        if (! $project->getState()->isRunning()) {
            // Idempotency: If already stopped, just return
            return;
        }

        // 2. Update State (Transient)
        $project->setState(ProjectStateEnum::STOPPING);

        // 3. Execute Infrastructure Change
        try {
            $success = $this->orchestrator->stop($project);

            if ($success) {
                $project->setState(ProjectStateEnum::READY);
            } else {
                $project->setState(ProjectStateEnum::ERROR);
                throw new RuntimeException('Failed to stop project containers.');
            }
        } catch (Throwable $e) {
            $project->setState(ProjectStateEnum::ERROR);
            throw $e;
        }
    }

    /**
     * Get the current status from the orchestrator and sync it with the project object.
     */
    public function syncState(Project $project): void
    {
        $services = $this->orchestrator->status($project);

        // Simple logic: if we have running services, we are RUNNING
        if (count($services) > 0) {
            $project->setState(ProjectStateEnum::RUNNING);
        } else {
            $project->setState(ProjectStateEnum::READY);
        }
    }
}
