<?php

declare(strict_types=1);

/**
 * LocalStatusCommand Feature Tests
 *
 * Tests the `local:status` command which displays the status of project services.
 *
 * @see \App\Commands\Local\StatusCommand
 */

use App\Commands\Local\StatusCommand;
use App\Contracts\OrchestratorInterface;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeDockerOrchestrator;

function createStatusTestProject(array $configOverrides = []): string
{
    $dir = sys_get_temp_dir() . '/tuti-status-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/.tuti', 0755, true);

    $config = [
        'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
        'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
    ];

    if ($configOverrides !== []) {
        $config = array_replace_recursive($config, $configOverrides);
    }

    file_put_contents(
        $dir . '/.tuti/config.json',
        json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    return $dir;
}

// ─── Registration ───────────────────────────────────────────────────────

describe('LocalStatusCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command->getName())->toBe('local:status');
    });

    it('uses HasBrandedOutput and BuildsProjectUrls traits', function (): void {
        $command = $this->app->make(StatusCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)
            ->toContain(\App\Concerns\HasBrandedOutput::class)
            ->toContain(\App\Concerns\BuildsProjectUrls::class);
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command->getDescription())->toBe('Check the status of project services');
    });
});

// ─── Running Services ─────────────────────────────────────────────────

describe('LocalStatusCommand Running Services', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStatusTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->fakeOrchestrator->statusResponse = [
            ['Name' => 'myproject-app-1', 'Service' => 'app', 'State' => 'running', 'Status' => 'Up 10 minutes', 'Ports' => '9000/tcp'],
            ['Name' => 'myproject-nginx-1', 'Service' => 'nginx', 'State' => 'running', 'Status' => 'Up 10 minutes', 'Ports' => '80/tcp, 443/tcp'],
            ['Name' => 'myproject-postgres-1', 'Service' => 'postgres', 'State' => 'running', 'Status' => 'Up 10 minutes (healthy)', 'Ports' => '5432/tcp'],
        ];
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('displays running services', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS);
    });

    it('shows service names in output', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('app')
            ->expectsOutputToContain('nginx')
            ->expectsOutputToContain('postgres');
    });

    it('displays project name', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('test-project');
    });

    it('calls orchestrator status', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->statusCalled)->toBeTrue();
    });

    it('displays project URLs', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://test-project.local.test')
            ->expectsOutputToContain('https://traefik.local.test');
    });

    it('displays service URLs when services are configured', function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);

        $this->testDir = createStatusTestProject([
            'environments' => [
                'current' => 'dev',
                'dev' => [
                    'services' => [
                        'mail' => ['mailpit'],
                        'search' => ['meilisearch'],
                    ],
                ],
            ],
        ]);
        chdir($this->testDir);

        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://mail.test-project.local.test')
            ->expectsOutputToContain('https://search.test-project.local.test');
    });
});

// ─── No Running Services ──────────────────────────────────────────────

describe('LocalStatusCommand No Running Services', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStatusTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->statusResponse = [];
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('shows warning when no services running', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('No running services found');
    });

    it('displays hint to start services', function (): void {
        $this->artisan('local:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('local:start');
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('LocalStatusCommand Error Handling', function (): void {

    it('returns failure when orchestrator throws', function (): void {
        $testDir = createStatusTestProject();
        $originalDir = getcwd();
        chdir($testDir);

        $mockOrchestrator = Mockery::mock(OrchestratorInterface::class);
        $mockOrchestrator->shouldReceive('status')
            ->andThrow(new RuntimeException('Docker not available'));
        $this->app->instance(OrchestratorInterface::class, $mockOrchestrator);

        $this->artisan('local:status')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to check status');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('returns failure when config is missing', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-status-err-' . bin2hex(random_bytes(4));
        mkdir($testDir . '/.tuti', 0755, true);
        // No config.json
        $originalDir = getcwd();
        chdir($testDir);

        $this->artisan('local:status')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to check status');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });
});
