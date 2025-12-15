<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Project\Project;

/**
 * Interface StateManagerInterface
 *
 * Defines the contract for managing project lifecycle states.
 */
interface StateManagerInterface
{
    /**
     * Start the project.
     */
    public function start(Project $project): void;

    /**
     * Stop the project.
     */
    public function stop(Project $project): void;

    /**
     * Sync the project state with the underlying infrastructure.
     */
    public function syncState(Project $project): void;
}
