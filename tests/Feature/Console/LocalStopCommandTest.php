<?php

declare(strict_types=1);

/**
 * LocalStopCommand Feature Tests
 *
 * Tests the `local:stop` command which stops the local development environment.
 *
 * @see StopCommand
 */

use App\Commands\Local\StopCommand;
use App\Contracts\OrchestratorInterface;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeDockerOrchestrator;

function createStopTestProject(array $configOverrides = []): string
{
    $dir = sys_get_temp_dir() . '/tuti-stop-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/.tuti', 0755, true);

    $config = array_merge_recursive([
        'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
        'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
    ], $configOverrides);

    file_put_contents(
        $dir . '/.tuti/config.json',
        json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    return $dir;
}

// ─── Registration ───────────────────────────────────────────────────────

describe('LocalStopCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command->getName())->toBe('local:stop');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(StopCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command->getDescription())->toBe('Stop the local development environment');
    });
});

// ─── Stop Running Project ─────────────────────────────────────────────

describe('LocalStopCommand Stop Running Project', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStopTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->fakeOrchestrator->statusResponse = [
            ['Name' => 'test-app', 'Service' => 'app', 'State' => 'running', 'Status' => 'Up 5 minutes', 'Ports' => ''],
        ];
        $this->fakeOrchestrator->stopResult = true;
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('stops running containers successfully', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays project name', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('test-project');
    });

    it('displays success message', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Project stopped successfully');
    });

    it('calls orchestrator stop', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->stopCalled)->toBeTrue();
    });

    it('displays containers stopped message', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Containers stopped');
    });
});

// ─── Already Stopped ──────────────────────────────────────────────────

describe('LocalStopCommand Already Stopped', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStopTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->fakeOrchestrator->statusResponse = [];
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('succeeds when project is already stopped', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays skipped message', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Project is already stopped');
    });

    it('does not call orchestrator stop', function (): void {
        $this->artisan('local:stop')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->stopCalled)->toBeFalse();
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('LocalStopCommand Error Handling', function (): void {

    it('returns failure when stop fails', function (): void {
        $testDir = createStopTestProject();
        $originalDir = getcwd();
        chdir($testDir);

        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->statusResponse = [
            ['Name' => 'test-app', 'Service' => 'app', 'State' => 'running', 'Status' => 'Up', 'Ports' => ''],
        ];
        $fakeOrchestrator->stopResult = false;
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);

        $this->artisan('local:stop')
            ->assertExitCode(Command::FAILURE);

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('displays error message on stop failure', function (): void {
        $testDir = createStopTestProject();
        $originalDir = getcwd();
        chdir($testDir);

        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->statusResponse = [
            ['Name' => 'test-app', 'Service' => 'app', 'State' => 'running', 'Status' => 'Up', 'Ports' => ''],
        ];
        $fakeOrchestrator->stopResult = false;
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);

        $this->artisan('local:stop')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to stop project');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('returns failure when config is missing', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-stop-err-' . bin2hex(random_bytes(4));
        mkdir($testDir . '/.tuti', 0755, true);
        // No config.json created
        $originalDir = getcwd();
        chdir($testDir);

        $this->artisan('local:stop')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to stop project');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });
});
