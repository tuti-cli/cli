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

it('displays status of running services', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        [
            'Name' => 'test-app',
            'Service' => 'app',
            'State' => 'running',
            'Status' => 'Up 2 hours',
            'Publishers' => '',
        ],
        [
            'Name' => 'test-nginx',
            'Service' => 'nginx',
            'State' => 'running',
            'Status' => 'Up 2 hours',
            'Publishers' => '0.0.0.0:8080->80/tcp',
        ],
    ];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutputToContain('test-app')
        ->expectsOutputToContain('nginx')
        ->expectsOutputToContain('running');
});

it('displays project name', function (): void {
    $this->fakeOrchestrator->statusResponse = [];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutputToContain('test-project');
});

it('shows warning when no services are running', function (): void {
    $this->fakeOrchestrator->statusResponse = [];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutput('No running services found.');
});

it('displays service ports', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        [
            'Name' => 'test-nginx',
            'Service' => 'nginx',
            'State' => 'running',
            'Status' => 'Up 1 hour',
            'Publishers' => '0.0.0.0:8080->80/tcp',
        ],
    ];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutputToContain('8080');
});

it('calls orchestrator status method', function (): void {
    $this->fakeOrchestrator->statusResponse = [];

    $this->artisan('local:status')
        ->assertSuccessful();

    expect($this->fakeOrchestrator->statusCalled)->toBeTrue()
        ->and($this->fakeOrchestrator->lastProject)->not->toBeNull();
});

it('fails when not in a tuti project', function (): void {
    $this->removeProjectConfig();

    $this->artisan('local: status')
        ->assertFailed()
        ->expectsOutputToContain('Failed to check status');
});

it('displays table with correct headers', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        [
            'Name' => 'test-app',
            'Service' => 'app',
            'State' => 'running',
            'Status' => 'Up',
            'Publishers' => '',
        ],
    ];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Container')
        ->expectsOutputToContain('Service')
        ->expectsOutputToContain('State')
        ->expectsOutputToContain('Status')
        ->expectsOutputToContain('Ports');
});

it('handles multiple services correctly', function (): void {
    $this->fakeOrchestrator->statusResponse = [
        ['Name' => 'test-app', 'Service' => 'app', 'State' => 'running', 'Status' => 'Up', 'Publishers' => ''],
        ['Name' => 'test-nginx', 'Service' => 'nginx', 'State' => 'running', 'Status' => 'Up', 'Publishers' => ''],
        ['Name' => 'test-postgres', 'Service' => 'postgres', 'State' => 'running', 'Status' => 'Up', 'Publishers' => ''],
    ];

    $this->artisan('local:status')
        ->assertSuccessful()
        ->expectsOutputToContain('app')
        ->expectsOutputToContain('nginx')
        ->expectsOutputToContain('postgres');
});
