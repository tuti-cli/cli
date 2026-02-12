<?php

declare(strict_types=1);

/**
 * StackComposeBuilderService YAML Validation Tests
 *
 * Phase 1: Validate generated Docker Compose files are syntactically valid YAML.
 *
 * These tests verify that the YAML output from StackComposeBuilderService
 * is always valid and parseable, regardless of input combinations.
 *
 * @see StackComposeBuilderService
 */

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackStubLoaderService;
use App\Services\Storage\JsonFileService;
use Symfony\Component\Yaml\Yaml;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->stackDir = createTestDirectory();
    $this->outputDir = createTestDirectory();

    // Create fake stack structure with various services
    buildFakeStackForYamlTests($this->stackDir);

    // Wire up real dependencies
    $this->registry = new StackRegistryManagerService;
    $this->stubLoader = new StackStubLoaderService;
    $this->stackLoader = new StackLoaderService(new JsonFileService);

    $this->service = new StackComposeBuilderService(
        $this->registry,
        $this->stubLoader,
        $this->stackLoader,
    );

    $this->projectConfig = ['PROJECT_NAME' => 'yamltest'];
});

afterEach(function (): void {
    cleanupTestDirectory($this->stackDir);
    cleanupTestDirectory($this->outputDir);
});

// ─── Helpers ────────────────────────────────────────────────────────────

function buildFakeStackForYamlTests(string $stackDir): void
{
    // Create service stub directories
    $stubDirs = ['databases', 'cache', 'mail', 'search', 'storage', 'workers'];
    foreach ($stubDirs as $dir) {
        mkdir($stackDir . '/services/' . $dir, 0755, true);
    }

    // Write services/registry.json with comprehensive services
    $registry = [
        'version' => '1.0.0',
        'services' => [
            'databases' => [
                'postgres' => [
                    'name' => 'PostgreSQL',
                    'stub' => 'databases/postgres.stub',
                    'volumes' => ['postgres_data'],
                    'default_variables' => [
                        'POSTGRES_VERSION' => '17',
                        'DB_HOST' => 'postgres',
                    ],
                ],
                'mysql' => [
                    'name' => 'MySQL',
                    'stub' => 'databases/mysql.stub',
                    'volumes' => ['mysql_data'],
                    'default_variables' => [
                        'MYSQL_VERSION' => '8.0',
                    ],
                ],
            ],
            'cache' => [
                'redis' => [
                    'name' => 'Redis',
                    'stub' => 'cache/redis.stub',
                    'volumes' => ['redis_data'],
                    'default_variables' => [
                        'REDIS_VERSION' => '7',
                    ],
                ],
            ],
            'mail' => [
                'mailpit' => [
                    'name' => 'Mailpit',
                    'stub' => 'mail/mailpit.stub',
                    'volumes' => [],
                    'default_variables' => [],
                ],
            ],
            'search' => [
                'meilisearch' => [
                    'name' => 'Meilisearch',
                    'stub' => 'search/meilisearch.stub',
                    'volumes' => ['meilisearch_data'],
                    'default_variables' => [
                        'MEILISEARCH_VERSION' => 'v1.8',
                    ],
                ],
            ],
            'storage' => [
                'minio' => [
                    'name' => 'MinIO',
                    'stub' => 'storage/minio.stub',
                    'volumes' => ['minio_data'],
                    'default_variables' => [],
                ],
            ],
            'workers' => [
                'horizon' => [
                    'name' => 'Horizon',
                    'stub' => 'workers/horizon.stub',
                    'volumes' => [],
                    'default_variables' => [],
                    'depends_on' => ['redis'],
                ],
            ],
        ],
    ];

    file_put_contents(
        $stackDir . '/services/registry.json',
        json_encode($registry, JSON_PRETTY_PRINT),
    );

    // Write stack.json manifest
    $manifest = [
        'name' => 'yaml-test-stack',
        'version' => '1.0.0',
        'type' => 'php',
        'framework' => 'laravel',
        'required_services' => [],
        'optional_services' => [],
    ];

    file_put_contents(
        $stackDir . '/stack.json',
        json_encode($manifest, JSON_PRETTY_PRINT),
    );

    // Write all stub files with valid YAML
    file_put_contents($stackDir . '/services/databases/postgres.stub', <<<'STUB'
postgres:
  image: postgres:{{POSTGRES_VERSION}}-alpine
  container_name: {{PROJECT_NAME}}_postgres
  environment:
    POSTGRES_DB: laravel
    POSTGRES_USER: laravel
    POSTGRES_PASSWORD: secret
  volumes:
    - postgres_data:/var/lib/postgresql/data
  networks:
    - {{NETWORK_NAME}}
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U laravel"]
    interval: 10s
    timeout: 5s
    retries: 5
STUB);

    file_put_contents($stackDir . '/services/databases/mysql.stub', <<<'STUB'
mysql:
  image: mysql:{{MYSQL_VERSION}}
  container_name: {{PROJECT_NAME}}_mysql
  environment:
    MYSQL_ROOT_PASSWORD: root
    MYSQL_DATABASE: laravel
    MYSQL_USER: laravel
    MYSQL_PASSWORD: secret
  volumes:
    - mysql_data:/var/lib/mysql
  networks:
    - {{NETWORK_NAME}}
STUB);

    file_put_contents($stackDir . '/services/cache/redis.stub', <<<'STUB'
redis:
  image: redis:{{REDIS_VERSION}}-alpine
  container_name: {{PROJECT_NAME}}_redis
  command: redis-server --maxmemory {{REDIS_MAX_MEMORY}}
  volumes:
    - redis_data:/data
  networks:
    - {{NETWORK_NAME}}
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 10s
    timeout: 5s
    retries: 5
STUB);

    file_put_contents($stackDir . '/services/mail/mailpit.stub', <<<'STUB'
mailpit:
  image: axllent/mailpit:latest
  container_name: {{PROJECT_NAME}}_mailpit
  ports:
    - "1025:1025"
    - "8025:8025"
  networks:
    - {{NETWORK_NAME}}
STUB);

    file_put_contents($stackDir . '/services/search/meilisearch.stub', <<<'STUB'
meilisearch:
  image: getmeili/meilisearch:{{MEILISEARCH_VERSION}}
  container_name: {{PROJECT_NAME}}_meilisearch
  environment:
    MEILI_MASTER_KEY: masterKey
  volumes:
    - meilisearch_data:/meili_data
  networks:
    - {{NETWORK_NAME}}
STUB);

    file_put_contents($stackDir . '/services/storage/minio.stub', <<<'STUB'
minio:
  image: minio/minio:latest
  container_name: {{PROJECT_NAME}}_minio
  command: server /data --console-address ":9001"
  environment:
    MINIO_ROOT_USER: minioadmin
    MINIO_ROOT_PASSWORD: minioadmin
  volumes:
    - minio_data:/data
  networks:
    - {{NETWORK_NAME}}
STUB);

    file_put_contents($stackDir . '/services/workers/horizon.stub', <<<'STUB'
horizon:
  image: {{PROJECT_NAME}}_app
  container_name: {{PROJECT_NAME}}_horizon
  command: php artisan horizon
  working_dir: /var/www/html
  networks:
    - {{NETWORK_NAME}}
  depends_on:
    redis:
      condition: service_healthy
STUB);
}

// ─── toYaml() Output Validation ────────────────────────────────────────
// Verify that toYaml() always produces valid, parseable YAML

describe('toYaml() produces valid YAML', function (): void {

    it('produces parseable YAML from empty compose structure', function (): void {
        $compose = [
            'services' => [],
            'networks' => [],
            'volumes' => [],
        ];

        $yaml = $this->service->toYaml($compose);

        // Must be a string
        expect($yaml)->toBeString();

        // Must be parseable without exceptions
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Must have expected top-level keys
        expect($parsed)
            ->toHaveKey('services')
            ->toHaveKey('networks')
            ->toHaveKey('volumes');
    });

    it('produces parseable YAML from single service', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);

        // Must be parseable
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Must contain the service
        expect($parsed['services'])->toHaveKey('postgres');
    });

    it('produces parseable YAML from multiple services', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis', 'mail.mailpit'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);

        // Must be parseable
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Must contain all services
        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis')
            ->toHaveKey('mailpit');
    });

    it('produces parseable YAML from all available services', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'databases.mysql', 'cache.redis', 'mail.mailpit', 'search.meilisearch', 'storage.minio'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);

        // Must be parseable without errors
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Verify all services are present
        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('mysql')
            ->toHaveKey('redis')
            ->toHaveKey('mailpit')
            ->toHaveKey('meilisearch')
            ->toHaveKey('minio');
    });

    it('produces parseable YAML with volumes', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);

        $parsed = Yaml::parse($yaml);

        // Must have volumes
        expect($parsed['volumes'])
            ->toHaveKey('postgres_data')
            ->toHaveKey('redis_data');
    });

    it('produces parseable YAML with complex nested structures', function (): void {
        $compose = [
            'services' => [
                'app' => [
                    'build' => [
                        'context' => '.',
                        'dockerfile' => 'Dockerfile',
                        'args' => [
                            'PHP_VERSION' => '8.4',
                            'VARIANT' => 'fpm',
                        ],
                    ],
                    'environment' => [
                        'APP_NAME' => 'Test',
                        'DB_HOST' => 'postgres',
                    ],
                    'healthcheck' => [
                        'test' => ['CMD', 'curl', '-f', 'http://localhost/health'],
                        'interval' => '30s',
                        'timeout' => '10s',
                        'retries' => 3,
                        'start_period' => '40s',
                    ],
                    'depends_on' => [
                        'postgres' => [
                            'condition' => 'service_healthy',
                        ],
                    ],
                ],
            ],
            'networks' => [
                'app_network' => [
                    'driver' => 'bridge',
                    'name' => 'test_network',
                ],
            ],
            'volumes' => [
                'data' => [
                    'driver' => 'local',
                    'name' => 'test_data',
                ],
            ],
        ];

        $yaml = $this->service->toYaml($compose);

        // Must be parseable
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Verify nested structures preserved
        expect($parsed['services']['app']['build']['args']['PHP_VERSION'])->toBe('8.4');
        expect($parsed['services']['app']['healthcheck']['retries'])->toBe(3);
        expect($parsed['services']['app']['depends_on']['postgres']['condition'])->toBe('service_healthy');
    });
});

// ─── writeToFile() Validation ──────────────────────────────────────────
// Verify that written files are valid YAML

describe('writeToFile() produces valid YAML files', function (): void {

    it('writes valid YAML file that can be parsed', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig
        );

        $outputPath = $this->outputDir . '/docker-compose.yml';
        $this->service->writeToFile($compose, $outputPath);

        // File must exist
        expect($outputPath)->toBeFile();

        // Content must be valid YAML
        $content = file_get_contents($outputPath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
        expect($parsed['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis');
    });

    it('writes valid YAML file for dev compose', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
            'dev'
        );

        $outputPath = $this->outputDir . '/docker-compose.dev.yml';
        $this->service->writeToFile($compose, $outputPath);

        $content = file_get_contents($outputPath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toHaveKey('postgres');
    });

    it('writes valid YAML file for production compose', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig,
            'production'
        );

        $outputPath = $this->outputDir . '/docker-compose.prod.yml';
        $this->service->writeToFile($compose, $outputPath);

        $content = file_get_contents($outputPath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
    });
});

// ─── Environment-Specific YAML Validation ──────────────────────────────
// Verify YAML is valid across all environments

describe('environment-specific YAML generation', function (): void {

    it('produces valid YAML for dev environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'dev'
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['redis']['command'])->toContain('256mb');
    });

    it('produces valid YAML for staging environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'staging'
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['redis']['command'])->toContain('512mb');
    });

    it('produces valid YAML for production environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'production'
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['redis']['command'])->toContain('1024mb');
    });
});

// ─── Edge Cases ────────────────────────────────────────────────────────
// Test edge cases that could produce invalid YAML

describe('edge cases produce valid YAML', function (): void {

    it('handles special characters in project name', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            ['PROJECT_NAME' => 'my-app-2024'],
        );

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['postgres']['container_name'])->toBe('my-app-2024_postgres');
    });

    it('handles empty service list', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build([], $this->projectConfig);

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services'])->toBeEmpty();
    });

    it('handles services with special YAML characters in values', function (): void {
        $compose = [
            'services' => [
                'app' => [
                    'environment' => [
                        'SPECIAL_VALUE' => 'value:with:colons',
                        'ANOTHER' => 'value-with-dashes-and_underscores',
                        'QUOTED' => 'needs "quotes" around',
                    ],
                ],
            ],
            'networks' => [],
            'volumes' => [],
        ];

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['app']['environment']['SPECIAL_VALUE'])->toBe('value:with:colons');
    });

    it('handles services with numeric ports', function (): void {
        $compose = [
            'services' => [
                'web' => [
                    'ports' => [
                        '80:80',
                        '443:443',
                    ],
                ],
            ],
            'networks' => [],
            'volumes' => [],
        ];

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['web']['ports'])->toContain('80:80');
    });

    it('handles deeply nested configuration', function (): void {
        $compose = [
            'services' => [
                'app' => [
                    'deploy' => [
                        'resources' => [
                            'limits' => [
                                'cpus' => '0.5',
                                'memory' => '512M',
                            ],
                            'reservations' => [
                                'cpus' => '0.25',
                                'memory' => '256M',
                            ],
                        ],
                        'restart_policy' => [
                            'condition' => 'on-failure',
                            'delay' => '5s',
                            'max_attempts' => 3,
                        ],
                    ],
                ],
            ],
            'networks' => [],
            'volumes' => [],
        ];

        $yaml = $this->service->toYaml($compose);
        $parsed = Yaml::parse($yaml);

        expect($parsed)->toBeArray();
        expect($parsed['services']['app']['deploy']['resources']['limits']['memory'])->toBe('512M');
        expect($parsed['services']['app']['deploy']['restart_policy']['max_attempts'])->toBe(3);
    });
});

// ─── Round-Trip Validation ─────────────────────────────────────────────
// Verify that build -> toYaml -> parse preserves data

describe('round-trip YAML validation', function (): void {

    it('preserves all data through build -> toYaml -> parse cycle', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $originalCompose = $this->service->build(
            ['databases.postgres', 'cache.redis', 'mail.mailpit'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($originalCompose);
        $parsedCompose = Yaml::parse($yaml);

        // Services should be preserved
        expect(array_keys($parsedCompose['services']))
            ->toEqualCanonicalizing(array_keys($originalCompose['services']));

        // Networks should be preserved
        expect(array_keys($parsedCompose['networks']))
            ->toEqualCanonicalizing(array_keys($originalCompose['networks']));

        // Volumes should be preserved
        expect(array_keys($parsedCompose['volumes']))
            ->toEqualCanonicalizing(array_keys($originalCompose['volumes']));
    });

    it('preserves service configuration through round-trip', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $originalCompose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($originalCompose);
        $parsedCompose = Yaml::parse($yaml);

        // Verify specific service config is preserved
        expect($parsedCompose['services']['postgres']['image'])
            ->toBe($originalCompose['services']['postgres']['image']);

        expect($parsedCompose['services']['postgres']['container_name'])
            ->toBe($originalCompose['services']['postgres']['container_name']);

        expect($parsedCompose['services']['postgres']['networks'])
            ->toBe($originalCompose['services']['postgres']['networks']);
    });
});

// ─── Stress Tests ──────────────────────────────────────────────────────
// Test with maximum services to ensure YAML stays valid

describe('stress tests with many services', function (): void {

    it('produces valid YAML with maximum realistic services', function (): void {
        $this->registry->loadForStack($this->stackDir);

        // Build with all available services
        $compose = $this->service->build(
            [
                'databases.postgres',
                'databases.mysql',
                'cache.redis',
                'mail.mailpit',
                'search.meilisearch',
                'storage.minio',
            ],
            $this->projectConfig
        );

        $yaml = $this->service->toYaml($compose);

        // Must be parseable
        $parsed = Yaml::parse($yaml);
        expect($parsed)->toBeArray();

        // Count services
        expect($parsed['services'])->toHaveCount(6);

        // Count volumes (postgres, mysql, redis, meilisearch, minio)
        expect($parsed['volumes'])->toHaveCount(5);
    });
});

// ─── Integration: Real Stack Validation ────────────────────────────────
// Validate against actual stack compose files

describe('real stack compose file validation', function (): void {

    it('parses Laravel docker-compose.yml without errors', function (): void {
        $composePath = base_path('stubs/stacks/laravel/docker-compose.yml');

        if (! file_exists($composePath)) {
            $this->markTestSkipped('Laravel stack compose file not found');
        }

        $content = file_get_contents($composePath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
        expect($parsed)->toHaveKey('services');
        expect($parsed)->toHaveKey('networks');
        expect($parsed)->toHaveKey('volumes');
    });

    it('parses Laravel docker-compose.dev.yml without errors', function (): void {
        $composePath = base_path('stubs/stacks/laravel/docker-compose.dev.yml');

        if (! file_exists($composePath)) {
            $this->markTestSkipped('Laravel dev compose file not found');
        }

        $content = file_get_contents($composePath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
    });

    it('parses WordPress docker-compose.yml without errors', function (): void {
        $composePath = base_path('stubs/stacks/wordpress/docker-compose.yml');

        if (! file_exists($composePath)) {
            $this->markTestSkipped('WordPress stack compose file not found');
        }

        $content = file_get_contents($composePath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
        expect($parsed)->toHaveKey('services');
    });

    it('parses WordPress docker-compose.dev.yml without errors', function (): void {
        $composePath = base_path('stubs/stacks/wordpress/docker-compose.dev.yml');

        if (! file_exists($composePath)) {
            $this->markTestSkipped('WordPress dev compose file not found');
        }

        $content = file_get_contents($composePath);
        $parsed = Yaml::parse($content);

        expect($parsed)->toBeArray();
    });
});
