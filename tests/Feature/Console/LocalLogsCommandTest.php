<?php

declare(strict_types=1);

/**
 * LocalLogsCommand Feature Tests
 *
 * Tests the `local:logs` command which views or follows logs for project services.
 *
 * @see \App\Commands\Local\LogsCommand
 */

use App\Commands\Local\LogsCommand;
use App\Contracts\OrchestratorInterface;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeDockerOrchestrator;

function createLogsTestProject(): string
{
    $dir = sys_get_temp_dir() . '/tuti-logs-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/.tuti', 0755, true);

    file_put_contents(
        $dir . '/.tuti/config.json',
        json_encode([
            'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
            'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    return $dir;
}

// ─── Registration ───────────────────────────────────────────────────────

describe('LocalLogsCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(LogsCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(LogsCommand::class);

        expect($command->getName())->toBe('local:logs');
    });

    it('has service argument', function (): void {
        $command = $this->app->make(LogsCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('service'))->toBeTrue()
            ->and($definition->getArgument('service')->isRequired())->toBeFalse();
    });

    it('has --follow option', function (): void {
        $command = $this->app->make(LogsCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('follow'))->toBeTrue();
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(LogsCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    it('has correct description', function (): void {
        $command = $this->app->make(LogsCommand::class);

        expect($command->getDescription())->toBe('View or follow logs for the project services');
    });
});

// ─── Log Retrieval ────────────────────────────────────────────────────

describe('LocalLogsCommand Log Retrieval', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLogsTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('fetches logs for all services', function (): void {
        $this->artisan('local:logs')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
            ->and($this->fakeOrchestrator->lastService)->toBeNull();
    });

    it('fetches logs for specific service', function (): void {
        $this->artisan('local:logs', ['service' => 'nginx'])
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->logsCalled)->toBeTrue()
            ->and($this->fakeOrchestrator->lastService)->toBe('nginx');
    });

    it('passes follow option to orchestrator', function (): void {
        $this->artisan('local:logs', ['--follow' => true])
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->lastFollow)->toBeTrue();
    });

    it('does not follow by default', function (): void {
        $this->artisan('local:logs')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->lastFollow)->toBeFalse();
    });

    it('passes project to orchestrator', function (): void {
        $this->artisan('local:logs')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->lastProject)->not->toBeNull()
            ->and($this->fakeOrchestrator->lastProject->getName())->toBe('test-project');
    });

    it('combines service and follow options', function (): void {
        $this->artisan('local:logs', ['service' => 'app', '--follow' => true])
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->lastService)->toBe('app')
            ->and($this->fakeOrchestrator->lastFollow)->toBeTrue();
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('LocalLogsCommand Error Handling', function (): void {

    it('returns failure when orchestrator throws', function (): void {
        $testDir = createLogsTestProject();
        $originalDir = getcwd();
        chdir($testDir);

        $mockOrchestrator = Mockery::mock(OrchestratorInterface::class);
        $mockOrchestrator->shouldReceive('logs')
            ->andThrow(new RuntimeException('Docker not running'));
        $this->app->instance(OrchestratorInterface::class, $mockOrchestrator);

        $this->artisan('local:logs')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to retrieve logs');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('returns failure when config is missing', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-logs-err-' . bin2hex(random_bytes(4));
        mkdir($testDir . '/.tuti', 0755, true);
        // No config.json
        $originalDir = getcwd();
        chdir($testDir);

        $this->artisan('local:logs')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to retrieve logs');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });
});
