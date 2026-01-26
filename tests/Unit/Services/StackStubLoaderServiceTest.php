<?php

declare(strict_types=1);

use App\Services\Stack\StackStubLoaderService;

it('loads postgres stub file', function (): void {
    $loader = app(StackStubLoaderService::class);

    $content = $loader->load('databases/postgres.stub', [
        'POSTGRES_VERSION' => '17',
        'NETWORK_NAME' => 'test_network',
        'DEPLOY_CONFIG' => '',
    ]);

    expect($content)
        ->toContain('postgres:')
        ->toContain('postgres:17-alpine')
        ->toContain('test_network');
});

it('loads redis stub file', function (): void {
    $loader = app(StackStubLoaderService::class);

    $content = $loader->load('cache/redis.stub', [
        'REDIS_VERSION' => '7',
        'NETWORK_NAME' => 'test_network',
        'REDIS_APPEND_ONLY' => '--appendonly yes',
        'REDIS_MAX_MEMORY' => '--maxmemory 256mb',
        'REDIS_EVICTION_POLICY' => '--maxmemory-policy allkeys-lru',
        'REDIS_PASSWORD_CONFIG' => '',
        'DEPLOY_CONFIG' => '',
    ]);

    expect($content)
        ->toContain('redis:')
        ->toContain('redis:7-alpine')
        ->toContain('test_network');
});

it('loads mailpit stub file', function (): void {
    $loader = app(StackStubLoaderService::class);

    $content = $loader->load('mail/mailpit.stub', [
        'MAILPIT_VERSION' => 'latest',
        'NETWORK_NAME' => 'test_network',
        'DEPLOY_CONFIG' => '',
    ]);

    expect($content)
        ->toContain('mailpit:')
        ->toContain('axllent/mailpit');
});

it('loads minio stub file', function (): void {
    $loader = app(StackStubLoaderService::class);

    $content = $loader->load('storage/minio.stub', [
        'MINIO_VERSION' => 'latest',
        'NETWORK_NAME' => 'test_network',
        'DEPLOY_CONFIG' => '',
    ]);

    expect($content)
        ->toContain('minio:')
        ->toContain('minio/minio');
});

it('throws exception for non-existent stub', function (): void {
    $loader = app(StackStubLoaderService::class);

    $loader->load('nonexistent/service.stub');
})->throws(RuntimeException::class, 'Stub file not found');

it('can detect unreplaced placeholders', function (): void {
    $loader = app(StackStubLoaderService::class);

    $content = $loader->load('databases/postgres.stub', []);
    $unreplaced = $loader->getUnreplacedPlaceholders($content);

    expect($unreplaced)
        ->toContain('POSTGRES_VERSION')
        ->toContain('NETWORK_NAME')
        ->toContain('DEPLOY_CONFIG');
});
