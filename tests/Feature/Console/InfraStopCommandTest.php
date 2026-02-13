<?php

declare(strict_types=1);

/**
 * InfraStopCommand Feature Tests
 *
 * Tests the `infra:stop` command which stops the global infrastructure.
 *
 * @see StopCommand
 */

use App\Commands\Infrastructure\StopCommand;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

describe('InfraStopCommand', function (): void {

    beforeEach(function (): void {
        $this->fakeInfra = new FakeInfrastructureManager();
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command->getName())->toBe('infra:stop');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StopCommand::class);

        expect($command->getDescription())->toBe('Stop the global infrastructure (Traefik)');
    });

    it('has --force option', function (): void {
        $command = $this->app->make(StopCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(StopCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    // ─── Not Installed / Already Stopped ──────────────────────────────────

    it('succeeds when infrastructure is not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('not installed');
    });

    it('succeeds when infrastructure is already stopped', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('already stopped');
    });

    // ─── Stop with Force ──────────────────────────────────────────────────

    it('stops infrastructure with --force flag', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeInfra->stopCalled)->toBeTrue();
    });

    it('displays success message after stopping', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure stopped');
    });

    it('suggests how to restart after stopping', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('tuti infra:start');
    });

    // ─── Confirmation ─────────────────────────────────────────────────────

    it('displays warning about affecting projects without --force', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        // Note: Laravel Prompts confirm() doesn't work with expectsQuestion()
        // We test the warning output exists; confirmation tested manually
        $this->artisan('infra:stop')
            ->expectsOutputToContain('affect all running tuti projects');
    });

    // ─── Error Handling ───────────────────────────────────────────────────

    it('returns failure when stop throws exception', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->fakeInfra->setStopResult(false);

        $this->artisan('infra:stop', ['--force' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to stop infrastructure');
    });
});
