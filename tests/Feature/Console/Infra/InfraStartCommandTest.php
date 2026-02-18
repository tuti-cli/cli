<?php

declare(strict_types=1);

/**
 * InfraStartCommand Feature Tests
 *
 * Tests the `infra:start` command which starts the global infrastructure.
 *
 * @see StartCommand
 */

use App\Commands\Infrastructure\StartCommand;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

describe('InfraStartCommand', function (): void {

    beforeEach(function (): void {
        $this->fakeInfra = new FakeInfrastructureManager();
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command->getName())->toBe('infra:start');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command->getDescription())->toBe('Start the global infrastructure (Traefik)');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(StartCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    // ─── Not Installed ────────────────────────────────────────────────────

    it('fails when infrastructure is not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Infrastructure is not installed');
    });

    it('suggests running install when not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti install');
    });

    // ─── Already Running ──────────────────────────────────────────────────

    it('succeeds when infrastructure is already running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('already running');
    });

    it('displays dashboard URL when already running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });

    // ─── Start Infrastructure ─────────────────────────────────────────────

    it('starts infrastructure when installed but not running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeInfra->startCalled)->toBeTrue();
    });

    it('displays success message after starting', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure started');
    });

    it('displays dashboard URL after starting', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });

    // ─── Error Handling ───────────────────────────────────────────────────

    it('returns failure when start throws exception', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);
        $this->fakeInfra->setStartResult(false);

        $this->artisan('infra:start')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to start infrastructure');
    });
});
