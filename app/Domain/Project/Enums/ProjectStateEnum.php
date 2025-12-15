<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

/**
 * Enum ProjectStateEnum
 *
 * State Flow:
 * UNINITIALIZED -> READY -> STARTING -> RUNNING -> STOPPING -> READY
 */
enum ProjectStateEnum: string
{
    /** Project has no config or .tuti directory */
    case UNINITIALIZED = 'uninitialized';

    /** Project is configured but stopped */
    case READY = 'ready';

    /** Project is currently starting up (transient state) */
    case STARTING = 'starting';

    /** Project represents a running set of containers */
    case RUNNING = 'running';

    /** Project is currently shutting down (transient state) */
    case STOPPING = 'stopping';

    /** Project encountered a critical error */
    case ERROR = 'error';

    /**
     * Check if the state represents a running/active project
     */
    public function isRunning(): bool
    {
        return $this === self::RUNNING || $this === self::STARTING;
    }
}
