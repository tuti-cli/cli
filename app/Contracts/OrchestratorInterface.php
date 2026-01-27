<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Project\Project;

/**
 * Interface OrchestratorInterface
 *
 * This contract abstracts the underlying container orchestration system.
 * By using an interface, our business logic (Commands/Services) doesn't need to know
 * that we are using Docker Compose locally. It simply asks the orchestrator to "start"
 * or "stop" a project.
 *
 * Why this matters:
 * 1. Testability: We can mock this interface to test our commands without running real Docker.
 * 2. Flexibility: In the future, we could add a `KubernetesOrchestrator` or `PodmanOrchestrator`
 *    without rewriting the rest of the application.
 */
interface OrchestratorInterface
{
    /**
     * Start the project's containers.
     *
     * @param  Project  $project  The project to start.
     * @return bool True if started successfully, false otherwise.
     */
    public function start(Project $project): bool;

    /**
     * Stop the project's containers.
     *
     * @param  Project  $project  The project to stop.
     * @return bool True if stopped successfully, false otherwise.
     */
    public function stop(Project $project): bool;

    /**
     * Restart the project or a specific service.
     *
     * @param  Project  $project  The project context.
     * @param  string|null  $service  Optional specific service name to restart.
     * @return bool True if restarted successfully.
     */
    public function restart(Project $project, ?string $service = null): bool;

    /**
     * Get the status of the project's services.
     *
     * @param  Project  $project  The project to check.
     * @return array<int, array<string, mixed>> List of service statuses.
     */
    public function status(Project $project): array;

    /**
     * Stream logs for the project or a specific service.
     *
     * @param  Project  $project  The project context.
     * @param  string|null  $service  Optional specific service to follow.
     * @param  bool  $follow  Whether to keep the stream open (follow mode).
     */
    public function logs(Project $project, ?string $service = null, bool $follow = false): void;
}
