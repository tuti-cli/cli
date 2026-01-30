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

it('displays logs for all services by default', function (): void {
    $this->artisan('local:logs')
        ->assertSuccessful()
        ->expectsOutputToContain('[app]')
        ->expectsOutputToContain('[nginx]');

    expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastService)->toBeNull()
        ->and($this->fakeOrchestrator->lastFollow)->toBeFalse();
});

it('displays logs for a specific service', function (): void {
    $this->artisan('local:logs app')
        ->assertSuccessful()
        ->expectsOutputToContain('[app]');

    expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastService)->toBe('app');
});

it('supports follow mode with --follow flag', function (): void {
    $this->artisan('local:logs --follow')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastFollow)->toBeTrue();
});

it('supports follow mode with -f shortcut', function (): void {
    $this->artisan('local:logs -f')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->lastFollow)->toBeTrue();
});

it('combines service and follow options', function (): void {
    $this->artisan('local:logs nginx --follow')
        ->assertSuccessful()
        ->expectsOutputToContain('[nginx]');

    expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastService)->toBe('nginx')
        ->and($this->fakeOrchestrator->lastFollow)->toBeTrue();
});

it('displays fetching message', function (): void {
    $this->artisan('local:logs')
        ->assertSuccessful()
        ->expectsOutputToContain('Fetching logs for all services');
});

it('displays fetching message for specific service', function (): void {
    $this->artisan('local:logs postgres')
        ->assertSuccessful()
        ->expectsOutputToContain('Fetching logs for postgres');
});

it('fails when not in a tuti project', function (): void {
    $this->removeProjectConfig();

    $this->artisan('local:logs')
        ->assertFailed()
        ->expectsOutputToContain('Failed to retrieve logs');
});

it('passes correct project to orchestrator', function (): void {
    $this->artisan('local:logs')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->lastProject)->not->toBeNull()
        ->and($this->fakeOrchestrator->lastProject->getName())->toBe('test-project');
});

it('handles service name with hyphen', function (): void {
    $this->artisan('local:logs my-service')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->lastService)->toBe('my-service');
});
