<?php

declare(strict_types=1);

/**
 * InfraRestartCommand Feature Tests
 *
 * Tests the `infra:restart` command which restarts the global infrastructure.
 *
 * @see \App\Commands\Infrastructure\RestartCommand
 */

use App\Commands\Infrastructure\RestartCommand;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

describe('InfraRestartCommand', function (): void {

    beforeEach(function (): void {
        $this->fakeInfra = new FakeInfrastructureManager();
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(RestartCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(RestartCommand::class);

        expect($command->getName())->toBe('infra:restart');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(RestartCommand::class);

        expect($command->getDescription())->toBe('Restart the global infrastructure (Traefik)');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(RestartCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    // ─── Not Installed ────────────────────────────────────────────────────

    it('fails when infrastructure is not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Infrastructure is not installed');
    });

    it('suggests running install when not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti install');
    });

    // ─── Restart Infrastructure ───────────────────────────────────────────

    it('calls stop and start when restarting', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeInfra->stopCalled)->toBeTrue();
        expect($this->fakeInfra->startCalled)->toBeTrue();
    });

    it('displays success message after restart', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure restarted');
    });

    it('displays dashboard URL after restart', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });

    // ─── Error Handling ───────────────────────────────────────────────────

    it('returns failure when restart throws exception', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->fakeInfra->setStopResult(false);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to restart infrastructure');
    });

    it('returns failure when start fails during restart', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->fakeInfra->setStartResult(false);

        $this->artisan('infra:restart')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to restart infrastructure');
    });
});
