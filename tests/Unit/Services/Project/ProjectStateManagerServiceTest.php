<?php

declare(strict_types=1);

/**
 * ProjectStateManagerService Unit Tests
 *
 * Tests the "brain" of project lifecycle management — the state machine
 * that coordinates: validation → orchestration → state updates.
 *
 * State flow: UNINITIALIZED → READY → STARTING → RUNNING → STOPPING → READY
 *                                                            ↘ ERROR
 *
 * This is our first test using MOCKING. The real OrchestratorInterface
 * would call Docker, but we inject FakeDockerOrchestrator instead.
 * The fake tracks which methods were called and lets us configure
 * success/failure results — so we test the state machine logic without
 * Docker running.
 *
 * @see \App\Services\Project\ProjectStateManagerService
 * @see \Tests\Mocks\FakeDockerOrchestrator
 */

use App\Domain\Project\Enums\ProjectStateEnum;
use App\Domain\Project\Project;
use App\Domain\Project\ValueObjects\ProjectConfigurationVO;
use App\Services\Project\ProjectStateManagerService;
use Tests\Mocks\FakeDockerOrchestrator;

// ─── Setup ──────────────────────────────────────────────────────────────
// A fresh FakeDockerOrchestrator and ProjectStateManagerService per test.

beforeEach(function (): void {
    $this->orchestrator = new FakeDockerOrchestrator;
    $this->service = new ProjectStateManagerService($this->orchestrator);
});

// ─── Helper: create a Project in a given state ──────────────────────────

function makeProject(ProjectStateEnum $state = ProjectStateEnum::READY): Project
{
    $config = new ProjectConfigurationVO(
        name: 'test-project',
        type: 'php',
        version: '1.0.0',
        environments: ['dev'],
    );

    return new Project(
        path: '/tmp/test-project',
        config: $config,
        state: $state,
    );
}

// ─── start() ────────────────────────────────────────────────────────────
// Transitions: READY → STARTING → RUNNING (or ERROR on failure)

describe('start', function (): void {

    it('transitions a READY project to RUNNING', function (): void {
        $project = makeProject(ProjectStateEnum::READY);

        $this->service->start($project);

        expect($project->getState())->toBe(ProjectStateEnum::RUNNING);
    });

    it('calls the orchestrator start method', function (): void {
        $project = makeProject(ProjectStateEnum::READY);

        $this->service->start($project);

        expect($this->orchestrator->startCalled)->toBeTrue();
        expect($this->orchestrator->lastProject)->toBe($project);
    });

    it('throws when project is already RUNNING', function (): void {
        $project = makeProject(ProjectStateEnum::RUNNING);

        expect(fn () => $this->service->start($project))
            ->toThrow(RuntimeException::class, 'already running');
    });

    it('does not call orchestrator when already RUNNING', function (): void {
        $project = makeProject(ProjectStateEnum::RUNNING);

        try {
            $this->service->start($project);
        } catch (RuntimeException) {
            // expected
        }

        expect($this->orchestrator->startCalled)->toBeFalse();
    });

    it('transitions to ERROR when orchestrator fails', function (): void {
        $this->orchestrator->startResult = false;
        $project = makeProject(ProjectStateEnum::READY);

        try {
            $this->service->start($project);
        } catch (RuntimeException) {
            // expected
        }

        expect($project->getState())->toBe(ProjectStateEnum::ERROR);
    });

    it('throws RuntimeException when orchestrator fails', function (): void {
        $this->orchestrator->startResult = false;
        $project = makeProject(ProjectStateEnum::READY);

        expect(fn () => $this->service->start($project))
            ->toThrow(RuntimeException::class, 'Failed to start');
    });

    it('can start a project from ERROR state', function (): void {
        $project = makeProject(ProjectStateEnum::ERROR);

        $this->service->start($project);

        expect($project->getState())->toBe(ProjectStateEnum::RUNNING);
    });

    it('can start a project from UNINITIALIZED state', function (): void {
        $project = makeProject(ProjectStateEnum::UNINITIALIZED);

        $this->service->start($project);

        expect($project->getState())->toBe(ProjectStateEnum::RUNNING);
    });
});

// ─── stop() ─────────────────────────────────────────────────────────────
// Transitions: RUNNING → STOPPING → READY (or ERROR on failure)
// Idempotent: stopping an already-stopped project is a no-op.

describe('stop', function (): void {

    it('transitions a RUNNING project to READY', function (): void {
        $project = makeProject(ProjectStateEnum::RUNNING);

        $this->service->stop($project);

        expect($project->getState())->toBe(ProjectStateEnum::READY);
    });

    it('calls the orchestrator stop method', function (): void {
        $project = makeProject(ProjectStateEnum::RUNNING);

        $this->service->stop($project);

        expect($this->orchestrator->stopCalled)->toBeTrue();
        expect($this->orchestrator->lastProject)->toBe($project);
    });

    it('is idempotent — stopping a READY project is a no-op', function (): void {
        $project = makeProject(ProjectStateEnum::READY);

        $this->service->stop($project);

        expect($project->getState())->toBe(ProjectStateEnum::READY);
        expect($this->orchestrator->stopCalled)->toBeFalse();
    });

    it('is idempotent — stopping an UNINITIALIZED project is a no-op', function (): void {
        $project = makeProject(ProjectStateEnum::UNINITIALIZED);

        $this->service->stop($project);

        expect($project->getState())->toBe(ProjectStateEnum::UNINITIALIZED);
        expect($this->orchestrator->stopCalled)->toBeFalse();
    });

    it('transitions to ERROR when orchestrator fails', function (): void {
        $this->orchestrator->stopResult = false;
        $project = makeProject(ProjectStateEnum::RUNNING);

        try {
            $this->service->stop($project);
        } catch (RuntimeException) {
            // expected
        }

        expect($project->getState())->toBe(ProjectStateEnum::ERROR);
    });

    it('throws RuntimeException when orchestrator fails', function (): void {
        $this->orchestrator->stopResult = false;
        $project = makeProject(ProjectStateEnum::RUNNING);

        expect(fn () => $this->service->stop($project))
            ->toThrow(RuntimeException::class, 'Failed to stop');
    });

    it('stops a STARTING project (considered running)', function (): void {
        $project = makeProject(ProjectStateEnum::STARTING);

        $this->service->stop($project);

        expect($project->getState())->toBe(ProjectStateEnum::READY);
    });
});

// ─── syncState() ────────────────────────────────────────────────────────
// Queries the orchestrator for running services and updates the
// project state accordingly. This is how we "discover" the real state.

describe('syncState', function (): void {

    it('sets state to RUNNING when orchestrator reports services', function (): void {
        $this->orchestrator->statusResponse = [
            ['name' => 'app', 'status' => 'running'],
            ['name' => 'postgres', 'status' => 'running'],
        ];

        $project = makeProject(ProjectStateEnum::READY);

        $this->service->syncState($project);

        expect($project->getState())->toBe(ProjectStateEnum::RUNNING);
    });

    it('sets state to READY when orchestrator reports no services', function (): void {
        $this->orchestrator->statusResponse = [];

        $project = makeProject(ProjectStateEnum::RUNNING);

        $this->service->syncState($project);

        expect($project->getState())->toBe(ProjectStateEnum::READY);
    });

    it('calls the orchestrator status method', function (): void {
        $project = makeProject(ProjectStateEnum::READY);

        $this->service->syncState($project);

        expect($this->orchestrator->statusCalled)->toBeTrue();
        expect($this->orchestrator->lastProject)->toBe($project);
    });

    it('can sync from any state', function (): void {
        $this->orchestrator->statusResponse = [
            ['name' => 'app', 'status' => 'running'],
        ];

        $project = makeProject(ProjectStateEnum::ERROR);

        $this->service->syncState($project);

        expect($project->getState())->toBe(ProjectStateEnum::RUNNING);
    });
});
