<?php

declare(strict_types=1);

use App\Services\Stack\Installers\LaravelStackInstaller;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-laravel-installer-' . uniqid();
    mkdir($this->testDir);
});

afterEach(function (): void {
    if (property_exists($this, 'testDir') && $this->testDir !== null && is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('returns correct identifier', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->getIdentifier())->toBe('laravel');
});

it('returns correct name', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->getName())->toBe('Laravel Stack');
});

it('returns correct framework', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->getFramework())->toBe('laravel');
});

it('supports laravel and laravel-stack identifiers', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->supports('laravel'))->toBeTrue()
        ->and($installer->supports('laravel-stack'))->toBeTrue()
        ->and($installer->supports('wordpress'))->toBeFalse();
});

it('detects Laravel project', function (): void {
    // Create fake Laravel project structure
    mkdir($this->testDir . '/bootstrap');
    file_put_contents($this->testDir . '/artisan', '<?php // artisan');
    file_put_contents($this->testDir . '/composer.json', json_encode([
        'require' => ['laravel/framework' => '^11.0'],
    ]));
    file_put_contents($this->testDir . '/bootstrap/app.php', '<?php // app');

    $installer = app(LaravelStackInstaller::class);

    expect($installer->detectExistingProject($this->testDir))->toBeTrue();
});

it('does not detect non-Laravel project', function (): void {
    // Create a generic PHP project
    file_put_contents($this->testDir . '/composer.json', json_encode([
        'require' => ['symfony/console' => '^7.0'],
    ]));

    $installer = app(LaravelStackInstaller::class);

    expect($installer->detectExistingProject($this->testDir))->toBeFalse();
});

it('does not detect empty directory as Laravel project', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->detectExistingProject($this->testDir))->toBeFalse();
});

it('returns available modes', function (): void {
    $installer = app(LaravelStackInstaller::class);

    $modes = $installer->getAvailableModes();

    expect($modes)->toHaveKey('fresh')
        ->and($modes)->toHaveKey('existing');
});

it('throws exception when applying to non-Laravel project', function (): void {
    $installer = app(LaravelStackInstaller::class);

    $installer->applyToExisting($this->testDir);
})->throws(RuntimeException::class, 'No Laravel project detected');

it('throws exception for fresh install in non-empty directory', function (): void {
    file_put_contents($this->testDir . '/existing-file.txt', 'content');

    $installer = app(LaravelStackInstaller::class);

    $installer->installFresh($this->testDir, 'test-project');
})->throws(RuntimeException::class, 'is not empty');

it('returns stack path', function (): void {
    $installer = app(LaravelStackInstaller::class);

    expect($installer->getStackPath())->toContain('laravel-stack');
});

it('can get stack manifest', function (): void {
    $installer = app(LaravelStackInstaller::class);

    $manifest = $installer->getStackManifest();

    expect($manifest)->toBeArray()
        ->and($manifest['name'])->toBe('laravel-stack')
        ->and($manifest['framework'])->toBe('laravel');
});
