<?php

declare(strict_types=1);

use App\Contracts\OrchestratorInterface;
use Tests\Feature\Concerns\CreatesLocalProjectEnvironment;
use Tests\Mocks\FakeDockerOrchestrator;

uses(CreatesLocalProjectEnvironment::class);

beforeEach(function (): void {
    $this->setupLocalProject();

    $this->fakeOrchestrator = new FakeDockerOrchestrator();
    $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
});

afterEach(function (): void {
    $this->cleanupLocalProject();
});

it('stops the local development environment successfully', function (): void {
    // Simulate running containers
    $this->fakeOrchestrator->statusResponse = [
        [
            'Name' => 'test-app',
            'Service' => 'app',
            'State' => 'running',
            'Status' => 'Up 2 hours',
        ],
    ];

    $this->artisan('local:stop')
        ->assertSuccessful()
        ->expectsOutput('Project stopped successfully.');

    expect($this->fakeOrchestrator->stopCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->statusCalled)->toBeTrue();
});

it('handles already stopped project gracefully', function (): void {
    // No running containers
    $this->fakeOrchestrator->statusResponse = [];

    $this->artisan('local:stop')
        ->assertSuccessful()
        ->expectsOutput('Project is already stopped.');

    // Stop should not be called if already stopped
    expect($this->fakeOrchestrator->stopCalled)->toBeFalse();
});

it('syncs state before stopping', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        ['Name' => 'test-app', 'State' => 'running'],
    ];

    $this->artisan('local:stop')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->statusCalled)->toBeTrue();
});

it('fails when not in a tuti project', function (): void {
    $this->removeProjectConfig();

    $this->artisan('local:stop')
        ->assertFailed()
        ->expectsOutputToContain('Failed to stop project');
});

it('handles orchestrator failure', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        ['Name' => 'test-app', 'State' => 'running'],
    ];
    $this->fakeOrchestrator->stopResult = false;

    $this->artisan('local:stop')
        ->assertFailed()
        ->expectsOutputToContain('Failed to stop project');
});

it('passes correct project to orchestrator', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        ['Name' => 'test-app', 'State' => 'running'],
    ];

    $this->artisan('local:stop')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->lastProject)->not->toBeNull()
        ->and($this->fakeOrchestrator->lastProject->getName())->toBe('test-project');
});
