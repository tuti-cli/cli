<?php

declare(strict_types=1);

/**
 * InfraStatusCommand Feature Tests
 *
 * Tests the `infra:status` command which shows the status of global infrastructure.
 *
 * @see StatusCommand
 */

use App\Commands\Infrastructure\StatusCommand;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

describe('InfraStatusCommand', function (): void {

    beforeEach(function (): void {
        $this->fakeInfra = new FakeInfrastructureManager();
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command->getName())->toBe('infra:status');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StatusCommand::class);

        expect($command->getDescription())->toBe('Show status of the global infrastructure (Traefik)');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(StatusCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    // ─── Status Output ────────────────────────────────────────────────────

    it('always returns success', function (): void {
        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays traefik section', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Traefik Reverse Proxy');
    });

    it('displays docker network section', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Docker Network');
    });

    it('displays installed status as yes when installed', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Installed');
    });

    it('displays running status as yes when running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Running');
    });

    it('displays healthy status when running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Healthy');
    });

    it('displays stopped status when installed but not running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Stopped');
    });

    it('displays not installed status when not installed', function (): void {
        $this->fakeInfra->setInstalled(false);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Not installed');
    });

    it('displays dashboard URL when running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });

    it('displays start hint when not running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(false);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('tuti infra:start');
    });

    it('displays network name', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('traefik_proxy');
    });

    it('displays infrastructure path', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:status')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Path');
    });
});
