<?php

declare(strict_types=1);

use App\Services\Tuti\TutiDirectoryManagerService;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-feature-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        $manager = new TutiDirectoryManagerService($this->testDir);
        $manager->clean();
        @rmdir($this->testDir);
    }
});

it('fails when no stack is provided in non-interactive mode', function (): void {
    if (tuti_exists()) {
        $this->artisan('stack:init --no-interaction')
            ->assertFailed()
            ->expectsOutput('Project already initialized. ".tuti/" directory already exists in your project root.');
        return;
    }

    $this->artisan('stack:init --no-interaction')
        ->assertFailed()
        ->expectsOutput('No stack selected. Exiting.');
});

it('fails when stack does not exist', function (): void {
    $this->artisan('stack:init nonexistent-stack myapp --no-interaction')
        ->assertFailed();
});

it('creates .tuti directory on successful init', function (): void {
    // This test requires a real stack - we'll skip for now
    // or create a fixture stack
    $this->markTestSkipped('Requires stack fixture');
});

it('generates docker-compose.yml', function (): void {
    $this->markTestSkipped('Requires stack fixture');
});

it('generates tuti.json metadata', function (): void {
    $this->markTestSkipped('Requires stack fixture');
});
