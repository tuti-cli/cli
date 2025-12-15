<?php

declare(strict_types=1);

use App\Contracts\OrchestratorInterface;
use Tests\Feature\Concerns\CreatesLocalProjectEnvironment;
use Tests\Mocks\FakeDockerOrchestrator;

uses(CreatesLocalProjectEnvironment::class);

beforeEach(function (): void {
    $this->setupLocalProject();

    // Bind fake orchestrator
    $this->fakeOrchestrator = new FakeDockerOrchestrator();
    $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
});

afterEach(function (): void {
    $this->cleanupLocalProject();
});

it('starts the local development environment successfully', function (): void {
    $this->artisan('local:start')
        ->assertSuccessful()
        ->expectsOutput('Project is running!  🚀');

    expect($this->fakeOrchestrator->startCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastProject)->not->toBeNull()
        ->and($this->fakeOrchestrator->lastProject->getName())->toBe('test-project');
});

it('displays project name when starting', function (): void {
    $this->artisan('local:start')
        ->assertSuccessful()
        ->expectsOutputToContain('test-project');
});

it('suggests checking status after successful start', function (): void {
    $this->artisan('local:start')
        ->assertSuccessful()
        ->expectsOutput("Run 'tuti local:status' to see details.");
});

it('fails when not in a tuti project', function (): void {
    $this->removeProjectConfig();

    $this->artisan('local:start')
        ->assertFailed()
        ->expectsOutputToContain('Failed to start project');
});

it('fails when docker-compose.yml does not exist', function (): void {
    $this->removeDockerCompose();

    $this->fakeOrchestrator->startResult = false;

    $this->artisan('local:start')
        ->assertFailed()
        ->expectsOutputToContain('Failed to start project');
});

it('handles orchestrator failure gracefully', function (): void {
    $this->fakeOrchestrator->startResult = false;

    $this->artisan('local:start')
        ->assertFailed()
        ->expectsOutputToContain('Failed to start project');
});

it('displays starting message', function (): void {
    $this->artisan('local:start')
        ->assertSuccessful()
        ->expectsOutput('Starting local environment...');
});
