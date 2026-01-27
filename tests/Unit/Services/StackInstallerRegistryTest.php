<?php

declare(strict_types=1);

use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackInstallerRegistry;

it('can register an installer', function (): void {
    $registry = new StackInstallerRegistry();
    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('test-stack');

    $registry->register($installer);

    expect($registry->has('test-stack'))->toBeTrue();
});

it('can get an installer by identifier', function (): void {
    $registry = new StackInstallerRegistry();
    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('test-stack');
    $installer->shouldReceive('supports')->andReturn(false);

    $registry->register($installer);

    expect($registry->get('test-stack'))->toBe($installer);
});

it('can get an installer by supports method', function (): void {
    $registry = new StackInstallerRegistry();
    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('laravel');
    $installer->shouldReceive('supports')
        ->with('laravel-stack')
        ->andReturn(true);

    $registry->register($installer);

    expect($registry->get('laravel-stack'))->toBe($installer);
});

it('throws exception when installer not found', function (): void {
    $registry = new StackInstallerRegistry();

    $registry->get('nonexistent');
})->throws(InvalidArgumentException::class, 'Stack installer not found: nonexistent');

it('returns all installers', function (): void {
    $registry = new StackInstallerRegistry();

    $installer1 = Mockery::mock(StackInstallerInterface::class);
    $installer1->shouldReceive('getIdentifier')->andReturn('stack1');

    $installer2 = Mockery::mock(StackInstallerInterface::class);
    $installer2->shouldReceive('getIdentifier')->andReturn('stack2');

    $registry->register($installer1);
    $registry->register($installer2);

    expect($registry->all())->toHaveCount(2);
});

it('returns available stacks for selection', function (): void {
    $registry = new StackInstallerRegistry();

    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('laravel');
    $installer->shouldReceive('getName')->andReturn('Laravel Stack');
    $installer->shouldReceive('getDescription')->andReturn('Laravel with Docker');
    $installer->shouldReceive('getFramework')->andReturn('laravel');

    $registry->register($installer);

    $stacks = $registry->getAvailableStacks();

    expect($stacks)->toHaveKey('laravel')
        ->and($stacks['laravel'])->toBe([
            'name' => 'Laravel Stack',
            'description' => 'Laravel with Docker',
            'framework' => 'laravel',
        ]);
});

it('detects installer for project', function (): void {
    $registry = new StackInstallerRegistry();

    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('laravel');
    $installer->shouldReceive('detectExistingProject')
        ->with('/path/to/project')
        ->andReturn(true);

    $registry->register($installer);

    expect($registry->detectForProject('/path/to/project'))->toBe($installer);
});

it('returns null when no installer detects project', function (): void {
    $registry = new StackInstallerRegistry();

    $installer = Mockery::mock(StackInstallerInterface::class);
    $installer->shouldReceive('getIdentifier')->andReturn('laravel');
    $installer->shouldReceive('detectExistingProject')
        ->with('/path/to/project')
        ->andReturn(false);

    $registry->register($installer);

    expect($registry->detectForProject('/path/to/project'))->toBeNull();
});

it('is registered with LaravelStackInstaller in container', function (): void {
    $registry = app(StackInstallerRegistry::class);

    expect($registry->has('laravel'))->toBeTrue();
});
