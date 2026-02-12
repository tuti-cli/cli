<?php

declare(strict_types=1);

/**
 * Real Stack Stub YAML Validation Tests
 *
 * Tests that actual service stubs from stubs/stacks/ produce valid YAML
 * when loaded, processed, and combined. Uses real stubs instead of synthetic
 * test fixtures to catch issues with actual production files.
 *
 * @see StackComposeBuilderService
 * @see StackStubLoaderService
 */

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackStubLoaderService;
use App\Services\Storage\JsonFileService;
use Symfony\Component\Yaml\Yaml;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->stubLoader = new StackStubLoaderService;
    $this->registry = new StackRegistryManagerService;
    $this->stackLoader = new StackLoaderService(new JsonFileService);
    $this->service = new StackComposeBuilderService(
        $this->registry,
        $this->stubLoader,
        $this->stackLoader,
    );

    $this->projectConfig = ['PROJECT_NAME' => 'testproject'];

    // Real stack paths
    $this->laravelStackPath = base_path('stubs/stacks/laravel');
    $this->wordpressStackPath = base_path('stubs/stacks/wordpress');
});

// ─── Helper Functions ───────────────────────────────────────────────────

function getLaravelServices(): array
{
    return [
        'databases.postgres',
        'databases.mysql',
        'databases.mariadb',
        'cache.redis',
        'search.meilisearch',
        'search.typesense',
        'storage.minio',
        'mail.mailpit',
        'workers.scheduler',
        'workers.horizon',
    ];
}

function getWordPressServices(): array
{
    return [
        'databases.mariadb',
        'databases.mysql',
        'cache.redis',
        'storage.minio',
        'mail.mailpit',
        'cli.wpcli',
    ];
}

// ─── Laravel Stack Stub Validation ──────────────────────────────────────

describe('Laravel stack stubs produce valid YAML', function (): void {

    it('validates postgres stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('postgres');
        expect($parsed['volumes'])->toHaveKey('postgres_data');
    });

    it('validates mysql stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.mysql'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('mysql');
        expect($parsed['volumes'])->toHaveKey('mysql_data');
    });

    it('validates mariadb stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.mariadb'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('mariadb');
        expect($parsed['volumes'])->toHaveKey('mariadb_data');
    });

    it('validates redis stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('redis');
        expect($parsed['services']['redis']['healthcheck'])->toBeArray();
    });

    it('validates meilisearch stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['search.meilisearch'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('meilisearch');
        expect($parsed['volumes'])->toHaveKey('meilisearch_data');
    });

    it('validates typesense stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['search.typesense'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('typesense');
        expect($parsed['volumes'])->toHaveKey('typesense_data');
    });

    it('validates minio stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['storage.minio'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('minio');
        expect($parsed['volumes'])->toHaveKey('minio_data');
    });

    it('validates mailpit stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['mail.mailpit'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('mailpit');
    });

    it('validates scheduler stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['workers.scheduler'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('scheduler');
    });

    it('validates horizon stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['workers.horizon'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('horizon');
    });
});

// ─── WordPress Stack Stub Validation ────────────────────────────────────

describe('WordPress stack stubs produce valid YAML', function (): void {

    it('validates mariadb stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['databases.mariadb'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        // WordPress uses 'database' as service name (not 'mariadb')
        expect($parsed['services'])->toHaveKey('database');
        expect($parsed['services']['database']['image'])->toContain('mariadb');
    });

    it('validates mysql stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['databases.mysql'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        // WordPress uses 'database' as service name (not 'mysql')
        expect($parsed['services'])->toHaveKey('database');
        expect($parsed['services']['database']['image'])->toContain('mysql');
    });

    it('validates redis stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('redis');
    });

    it('validates minio stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['storage.minio'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('minio');
    });

    it('validates mailpit stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['mail.mailpit'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('mailpit');
    });

    it('validates wpcli stub produces valid YAML', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['cli.wpcli'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('wpcli');
        // wpcli uses profiles
        expect($parsed['services']['wpcli']['profiles'])->toContain('cli');
    });
});

// ─── Service Combination Tests ──────────────────────────────────────────

describe('Laravel service combinations produce valid YAML', function (): void {

    it('validates database + cache combination', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis');
        expect($parsed['volumes'])
            ->toHaveKey('postgres_data')
            ->toHaveKey('redis_data');
    });

    it('validates database + cache + mail combination', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis', 'mail.mailpit'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis')
            ->toHaveKey('mailpit');
    });

    it('validates database + cache + search combination', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis', 'search.meilisearch'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis')
            ->toHaveKey('meilisearch');
    });

    it('validates full stack combination with all services', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $services = [
            'databases.postgres',
            'cache.redis',
            'search.meilisearch',
            'storage.minio',
            'mail.mailpit',
            'workers.scheduler',
        ];

        $compose = $this->service->build($services, $this->projectConfig);

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])->toHaveCount(6);
        expect($parsed['volumes'])->toHaveCount(4); // postgres, redis, meilisearch, minio
    });
});

describe('WordPress service combinations produce valid YAML', function (): void {

    it('validates database + cache combination', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['databases.mariadb', 'cache.redis'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])
            ->toHaveKey('database') // WordPress uses 'database' as service name
            ->toHaveKey('redis');
    });

    it('validates database + cache + mail + storage combination', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            ['databases.mariadb', 'cache.redis', 'mail.mailpit', 'storage.minio'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services'])
            ->toHaveKey('database') // WordPress uses 'database' as service name
            ->toHaveKey('redis')
            ->toHaveKey('mailpit')
            ->toHaveKey('minio');
    });

    it('validates all WordPress services together', function (): void {
        $this->registry->loadForStack($this->wordpressStackPath);

        $compose = $this->service->build(
            getWordPressServices(),
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        // 6 services in registry, but mariadb and mysql both produce 'database'
        // So we expect 5 unique services (database, redis, minio, mailpit, wpcli)
        expect($parsed['services'])->toHaveCount(5);
        expect($parsed['services'])
            ->toHaveKey('database')
            ->toHaveKey('redis')
            ->toHaveKey('minio')
            ->toHaveKey('mailpit')
            ->toHaveKey('wpcli');
    });
});

// ─── Dependency Resolution Tests ────────────────────────────────────────

describe('service dependency resolution produces valid YAML', function (): void {

    it('resolves horizon → redis dependency automatically', function (): void {
        $compose = $this->service->buildWithStack(
            $this->laravelStackPath,
            ['workers.horizon'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        // Both horizon and redis should be present (redis auto-added)
        expect($parsed['services'])
            ->toHaveKey('horizon')
            ->toHaveKey('redis');
    });
});

// ─── Section-Based Stub Parsing ─────────────────────────────────────────

describe('section-based stub parsing produces valid YAML', function (): void {

    it('parses base section from real redis stub', function (): void {
        $stubPath = $this->laravelStackPath . '/services/cache/redis.stub';

        if (! file_exists($stubPath)) {
            $this->markTestSkipped('Redis stub not found');
        }

        $replacements = [
            'PROJECT_NAME' => 'testproject',
            'NETWORK_NAME' => 'testproject_network',
        ];

        $baseYaml = $this->stubLoader->loadSection($stubPath, 'base', $replacements);

        expect($baseYaml)->not->toBeNull();

        $parsed = Yaml::parse($baseYaml);
        expect($parsed)->toBeArray();
        expect($parsed)->toHaveKey('redis');
    });

    it('parses dev section from real redis stub', function (): void {
        $stubPath = $this->laravelStackPath . '/services/cache/redis.stub';

        if (! file_exists($stubPath)) {
            $this->markTestSkipped('Redis stub not found');
        }

        $replacements = [
            'PROJECT_NAME' => 'testproject',
            'NETWORK_NAME' => 'testproject_network',
        ];

        $devYaml = $this->stubLoader->loadSection($stubPath, 'dev', $replacements);

        expect($devYaml)->not->toBeNull();

        $parsed = Yaml::parse($devYaml);
        expect($parsed)->toBeArray();
        expect($parsed)->toHaveKey('redis');
    });

    it('parses volumes section from real redis stub', function (): void {
        $stubPath = $this->laravelStackPath . '/services/cache/redis.stub';

        if (! file_exists($stubPath)) {
            $this->markTestSkipped('Redis stub not found');
        }

        $replacements = [
            'PROJECT_NAME' => 'testproject',
            'NETWORK_NAME' => 'testproject_network',
        ];

        $volumesYaml = $this->stubLoader->loadSection($stubPath, 'volumes', $replacements);

        expect($volumesYaml)->not->toBeNull();

        $parsed = Yaml::parse($volumesYaml);
        expect($parsed)->toBeArray();
        expect($parsed)->toHaveKey('redis_data');
    });

    it('parses all sections from horizon stub', function (): void {
        $stubPath = $this->laravelStackPath . '/services/workers/horizon.stub';

        if (! file_exists($stubPath)) {
            $this->markTestSkipped('Horizon stub not found');
        }

        $replacements = [
            'PROJECT_NAME' => 'testproject',
            'NETWORK_NAME' => 'testproject_network',
        ];

        // Base section
        $baseYaml = $this->stubLoader->loadSection($stubPath, 'base', $replacements);
        expect($baseYaml)->not->toBeNull();
        $baseParsed = Yaml::parse($baseYaml);
        expect($baseParsed)->toHaveKey('horizon');

        // Dev section
        $devYaml = $this->stubLoader->loadSection($stubPath, 'dev', $replacements);
        expect($devYaml)->not->toBeNull();
        $devParsed = Yaml::parse($devYaml);
        expect($devParsed)->toHaveKey('horizon');
    });

    it('parses wpcli stub with profiles', function (): void {
        $stubPath = $this->wordpressStackPath . '/services/cli/wpcli.stub';

        if (! file_exists($stubPath)) {
            $this->markTestSkipped('WP-CLI stub not found');
        }

        $replacements = [
            'PROJECT_NAME' => 'testproject',
            'NETWORK_NAME' => 'testproject_network',
        ];

        $baseYaml = $this->stubLoader->loadSection($stubPath, 'base', $replacements);

        expect($baseYaml)->not->toBeNull();

        $parsed = Yaml::parse($baseYaml);
        expect($parsed)->toHaveKey('wpcli');
        expect($parsed['wpcli']['profiles'])->toContain('cli');
    });
});

// ─── Placeholder Replacement Validation ────────────────────────────────

describe('placeholder replacement in real stubs', function (): void {

    it('replaces {{PROJECT_NAME}} in redis stub', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['cache.redis'],
            ['PROJECT_NAME' => 'my-awesome-app']
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed['services']['redis']['container_name'])
            ->toContain('my-awesome-app');
    });

    it('replaces {{NETWORK_NAME}} in postgres stub', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres'],
            ['PROJECT_NAME' => 'testnet']
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        // Network name should include project name
        expect($parsed['services']['postgres']['networks'])
            ->toContain('testnet_network');
    });
});

// ─── Environment-Specific Generation ───────────────────────────────────

describe('environment-specific generation with real stubs', function (): void {

    it('produces valid YAML for dev environment', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig,
            'dev'
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('postgres');
        expect($parsed['services'])->toHaveKey('redis');
    });

    it('produces valid YAML for production environment', function (): void {
        $this->registry->loadForStack($this->laravelStackPath);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig,
            'production'
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('postgres');
        expect($parsed['services'])->toHaveKey('redis');
    });
});
