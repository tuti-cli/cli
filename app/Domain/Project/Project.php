<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Project\Enums\ProjectStateEnum;
use App\Domain\Project\ValueObjects\ProjectConfigurationVO;

/**
 * Class Project
 *
 * This is the CORE Domain Entity.
 * It represents a software project that Tuti manages.
 *
 * It aggregates:
 * 1. Identity (Name, Path)
 * 2. Configuration (What stack? What ports?)
 * 3. State (Is it running? Is it stopped?)
 *
 * By passing this `Project` object around instead of just paths or strings,
 * we ensure all parts of the app have consistent, validated data about the project.
 */
final class Project
{
    public function __construct(
        public readonly string $path,
        public readonly ProjectConfigurationVO $config,
        private ProjectStateEnum $state = ProjectStateEnum::UNINITIALIZED
    ) {}

    /**
     * Get the project name
     */
    public function getName(): string
    {
        return $this->config->name;
    }

    /**
     * Get the current state
     */
    public function getState(): ProjectStateEnum
    {
        return $this->state;
    }

    /**
     * Update the state (internal use for StateManager)
     */
    public function setState(ProjectStateEnum $state): void
    {
        $this->state = $state;
    }

    /**
     * Check if project is valid/initialized
     */
    public function isInitialized(): bool
    {
        return $this->state !== ProjectStateEnum::UNINITIALIZED;
    }
}
