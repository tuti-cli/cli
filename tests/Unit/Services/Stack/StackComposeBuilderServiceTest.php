<?php

declare(strict_types=1);

/**
 * StackComposeBuilderService Unit Tests
 *
 * Tests the Docker Compose YAML generator — the most complex service in
 * the codebase. It orchestrates three dependencies to build a full
 * docker-compose configuration from stack templates:
 *
 *   StackComposeBuilderService
 *       → StackRegistryManagerService  (reads services/registry.json)
 *       → StackStubLoaderService       (loads .stub files with {{placeholders}})
 *       → StackLoaderService           (reads stack.json manifest)
 *
 * Instead of mocking, we create a minimal fake stack directory in /tmp
 * with real registry.json, stack.json, and .stub files. This tests the
 * full pipeline end-to-end, which catches integration bugs mocks would miss.
 *
 * @see \App\Services\Stack\StackComposeBuilderService
 */

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackStubLoaderService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We build a fake stack directory with minimal but valid registry, manifest,
// and stub files. All three dependencies are real instances.

beforeEach(function (): void {
    $this->stackDir = createTestDirectory();
    $this->outputDir = createTestDirectory();

    // Create fake stack structure
    buildFakeStack($this->stackDir);

    // Wire up real dependencies
    $this->registry = new StackRegistryManagerService;
    $this->stubLoader = new StackStubLoaderService;
    $this->stackLoader = new StackLoaderService(new JsonFileService);

    $this->service = new StackComposeBuilderService(
        $this->registry,
        $this->stubLoader,
        $this->stackLoader,
    );

    // Default project config used by most tests
    $this->projectConfig = [
        'PROJECT_NAME' => 'myapp',
    ];
});

afterEach(function (): void {
    cleanupTestDirectory($this->stackDir);
    cleanupTestDirectory($this->outputDir);
});

// ─── Helpers: build a minimal but functional fake stack ──────────────────

function buildFakeStack(string $stackDir, array $manifestOverrides = []): void
{
    // 1. Create service stub directories
    $stubDirs = ['databases', 'cache', 'mail', 'workers'];

    foreach ($stubDirs as $dir) {
        mkdir($stackDir . '/services/' . $dir, 0755, true);
    }

    // 2. Write services/registry.json
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
            ],
            'cache' => [
                'redis' => [
                    'name' => 'Redis',
                    'stub' => 'cache/redis.stub',
                    'volumes' => ['redis_data'],
                    'default_variables' => [
                        'REDIS_VERSION' => '7',
                        'REDIS_HOST' => 'redis',
                    ],
                ],
            ],
            'mail' => [
                'mailpit' => [
                    'name' => 'Mailpit',
                    'stub' => 'mail/mailpit.stub',
                    'volumes' => [],
                    'default_variables' => [
                        'MAIL_HOST' => 'mailpit',
                    ],
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

    // 3. Write stack.json manifest
    $manifest = array_replace_recursive([
        'name' => 'test-stack',
        'version' => '1.0.0',
        'type' => 'php',
        'framework' => 'laravel',
        'required_services' => [
            'database' => [
                'category' => 'databases',
                'options' => ['postgres'],
                'default' => 'postgres',
            ],
        ],
        'optional_services' => [
            'cache' => [
                'category' => 'cache',
                'options' => ['redis'],
                'default' => null,
            ],
        ],
    ], $manifestOverrides);

    file_put_contents(
        $stackDir . '/stack.json',
        json_encode($manifest, JSON_PRETTY_PRINT),
    );

    // 4. Write minimal .stub files (valid YAML with {{placeholders}})
    file_put_contents($stackDir . '/services/databases/postgres.stub', <<<'STUB'
    postgres:
      image: postgres:{{POSTGRES_VERSION}}
      container_name: {{PROJECT_NAME}}_postgres
      networks:
        - {{NETWORK_NAME}}
    STUB);

    file_put_contents($stackDir . '/services/cache/redis.stub', <<<'STUB'
    redis:
      image: redis:{{REDIS_VERSION}}-alpine
      container_name: {{PROJECT_NAME}}_redis
      command: redis-server --maxmemory {{REDIS_MAX_MEMORY}}
      networks:
        - {{NETWORK_NAME}}
    STUB);

    file_put_contents($stackDir . '/services/mail/mailpit.stub', <<<'STUB'
    mailpit:
      image: axllent/mailpit:latest
      container_name: {{PROJECT_NAME}}_mailpit
      networks:
        - {{NETWORK_NAME}}
    STUB);

    file_put_contents($stackDir . '/services/workers/horizon.stub', <<<'STUB'
    horizon:
      image: {{PROJECT_NAME}}_app
      container_name: {{PROJECT_NAME}}_horizon
      command: php artisan horizon
      networks:
        - {{NETWORK_NAME}}
    STUB);
}

// ─── build() ────────────────────────────────────────────────────────────
// The core builder: creates base structure, then adds each selected
// service by loading its stub, replacing placeholders, and merging.

describe('build', function (): void {

    it('returns base structure with network and empty services', function (): void {
        // Load registry (required before build)
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build([], $this->projectConfig);

        expect($compose)
            ->toHaveKey('services')
            ->toHaveKey('networks')
            ->toHaveKey('volumes');

        expect($compose['services'])->toBeEmpty();
        expect($compose['networks'])->toHaveKey('myapp_network');
        expect($compose['networks']['myapp_network']['driver'])->toBe('bridge');
    });

    it('uses PROJECT_NAME for network naming', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build([], ['PROJECT_NAME' => 'cool-app']);

        expect($compose['networks'])->toHaveKey('cool-app_network');
    });

    it('defaults PROJECT_NAME to app when missing', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build([], []);

        expect($compose['networks'])->toHaveKey('app_network');
    });

    it('adds a single service from a stub', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        expect($compose['services'])->toHaveKey('postgres');
        expect($compose['services']['postgres']['image'])->toBe('postgres:17');
    });

    it('replaces {{PROJECT_NAME}} in stubs', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        expect($compose['services']['postgres']['container_name'])
            ->toBe('myapp_postgres');
    });

    it('replaces {{NETWORK_NAME}} in stubs', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        expect($compose['services']['postgres']['networks'])
            ->toContain('myapp_network');
    });

    it('applies default_variables from registry', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        // POSTGRES_VERSION=17 from registry default_variables
        expect($compose['services']['postgres']['image'])->toBe('postgres:17');
    });

    it('adds multiple services', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis', 'mail.mailpit'],
            $this->projectConfig,
        );

        expect($compose['services'])
            ->toHaveKey('postgres')
            ->toHaveKey('redis')
            ->toHaveKey('mailpit');
    });

    it('adds volumes for services that define them', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        expect($compose['volumes'])
            ->toHaveKey('postgres_data');

        expect($compose['volumes']['postgres_data']['driver'])->toBe('local');
        expect($compose['volumes']['postgres_data']['name'])->toBe('myapp_postgres_data');
    });

    it('does not add volumes for services without them', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['mail.mailpit'],
            $this->projectConfig,
        );

        expect($compose['volumes'])->toBeEmpty();
    });

    it('accumulates volumes from multiple services', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres', 'cache.redis'],
            $this->projectConfig,
        );

        expect($compose['volumes'])
            ->toHaveKey('postgres_data')
            ->toHaveKey('redis_data');
    });

    it('throws RuntimeException for invalid service key format', function (): void {
        $this->registry->loadForStack($this->stackDir);

        expect(fn () => $this->service->build(
            ['invalid-key-no-dot'],
            $this->projectConfig,
        ))->toThrow(RuntimeException::class, 'Invalid service key format');
    });
});

// ─── Redis-specific replacements ────────────────────────────────────────
// Redis gets environment-specific REDIS_MAX_MEMORY defaults when not
// already set by stack or environment overrides.

describe('Redis memory defaults', function (): void {

    it('sets REDIS_MAX_MEMORY to 256mb for dev environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'dev',
        );

        expect($compose['services']['redis']['command'])
            ->toContain('256mb');
    });

    it('sets REDIS_MAX_MEMORY to 1024mb for production environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'production',
        );

        expect($compose['services']['redis']['command'])
            ->toContain('1024mb');
    });

    it('sets REDIS_MAX_MEMORY to 512mb for staging environment', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['cache.redis'],
            $this->projectConfig,
            'staging',
        );

        expect($compose['services']['redis']['command'])
            ->toContain('512mb');
    });
});

// ─── buildWithStack() ───────────────────────────────────────────────────
// The full pipeline: loads registry + manifest, resolves dependencies,
// applies stack-level and environment-level overrides, then builds.

describe('buildWithStack', function (): void {

    it('runs the full pipeline end-to-end', function (): void {
        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['databases.postgres'],
            $this->projectConfig,
        );

        expect($compose['services'])->toHaveKey('postgres');
        expect($compose['networks'])->toHaveKey('myapp_network');
    });

    it('resolves dependencies automatically (horizon → redis)', function (): void {
        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['workers.horizon'],
            $this->projectConfig,
        );

        // Horizon depends on redis — redis should be auto-added
        expect($compose['services'])
            ->toHaveKey('horizon')
            ->toHaveKey('redis');
    });

    it('applies stack-level variable overrides', function (): void {
        // Rebuild stack with service_overrides in manifest
        cleanupTestDirectory($this->stackDir);
        $this->stackDir = createTestDirectory();

        buildFakeStack($this->stackDir, [
            'service_overrides' => [
                'databases.postgres' => [
                    'variables' => [
                        'POSTGRES_VERSION' => '16',
                    ],
                ],
            ],
        ]);

        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['databases.postgres'],
            $this->projectConfig,
        );

        // Stack override should win over registry default (17 → 16)
        expect($compose['services']['postgres']['image'])->toBe('postgres:16');
    });

    it('applies environment-level variable overrides', function (): void {
        cleanupTestDirectory($this->stackDir);
        $this->stackDir = createTestDirectory();

        buildFakeStack($this->stackDir, [
            'service_overrides' => [
                'cache.redis' => [
                    'variables' => [
                        'REDIS_MAX_MEMORY' => '512mb',
                    ],
                    'environments' => [
                        'production' => [
                            'variables' => [
                                'REDIS_MAX_MEMORY' => '2048mb',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['cache.redis'],
            $this->projectConfig,
            'production',
        );

        // Environment override (2048mb) wins over stack override (512mb)
        expect($compose['services']['redis']['command'])
            ->toContain('2048mb');
    });

    it('applies resource overrides from environment', function (): void {
        cleanupTestDirectory($this->stackDir);
        $this->stackDir = createTestDirectory();

        buildFakeStack($this->stackDir, [
            'service_overrides' => [
                'databases.postgres' => [
                    'environments' => [
                        'production' => [
                            'resources' => [
                                'limits' => [
                                    'memory' => '1G',
                                    'cpus' => '2.0',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['databases.postgres'],
            $this->projectConfig,
            'production',
        );

        expect($compose['services']['postgres']['deploy']['resources']['limits']['memory'])
            ->toBe('1G');
        expect($compose['services']['postgres']['deploy']['resources']['limits']['cpus'])
            ->toBe('2.0');
    });

    it('applies deploy overrides from environment', function (): void {
        cleanupTestDirectory($this->stackDir);
        $this->stackDir = createTestDirectory();

        buildFakeStack($this->stackDir, [
            'service_overrides' => [
                'databases.postgres' => [
                    'environments' => [
                        'production' => [
                            'deploy' => [
                                'replicas' => 2,
                                'restart_policy' => [
                                    'condition' => 'on-failure',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $compose = $this->service->buildWithStack(
            $this->stackDir,
            ['databases.postgres'],
            $this->projectConfig,
            'production',
        );

        expect($compose['services']['postgres']['deploy']['replicas'])->toBe(2);
        expect($compose['services']['postgres']['deploy']['restart_policy']['condition'])
            ->toBe('on-failure');
    });
});

// ─── toYaml() ───────────────────────────────────────────────────────────
// Converts the compose array to a YAML string using Symfony's Yaml dumper.

describe('toYaml', function (): void {

    it('returns a valid YAML string', function (): void {
        $compose = [
            'services' => [
                'app' => [
                    'image' => 'php:8.4',
                ],
            ],
            'networks' => [
                'app_network' => ['driver' => 'bridge'],
            ],
        ];

        $yaml = $this->service->toYaml($compose);

        expect($yaml)
            ->toBeString()
            ->toContain('services:')
            ->toContain("image: 'php:8.4'") // YAML quotes values with colons
            ->toContain('networks:');
    });

    it('handles empty compose structure', function (): void {
        $yaml = $this->service->toYaml([
            'services' => [],
            'networks' => [],
            'volumes' => [],
        ]);

        expect($yaml)->toBeString()->toContain('services:');
    });
});

// ─── writeToFile() ──────────────────────────────────────────────────────
// Writes the YAML output to a file on disk.

describe('writeToFile', function (): void {

    it('writes compose YAML to the specified file', function (): void {
        $compose = [
            'services' => [
                'app' => ['image' => 'myapp'],
            ],
        ];

        $outputPath = $this->outputDir . '/docker-compose.yml';

        $this->service->writeToFile($compose, $outputPath);

        expect($outputPath)->toBeFile();

        $content = file_get_contents($outputPath);

        expect($content)
            ->toContain('services:')
            ->toContain('image: myapp');
    });

    it('throws on invalid output path', function (): void {
        $compose = ['services' => []];

        // file_put_contents triggers ErrorException before code can return false
        expect(fn () => $this->service->writeToFile($compose, '/nonexistent/dir/file.yml'))
            ->toThrow(ErrorException::class);
    });
});

// ─── Override layering ──────────────────────────────────────────────────
// Variables are applied in layers: registry defaults → stack overrides
// → environment overrides. Each layer wins over the previous one.

describe('variable layering', function (): void {

    it('registry defaults are used when no overrides exist', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
        );

        // POSTGRES_VERSION=17 from registry defaults, no overrides
        expect($compose['services']['postgres']['image'])->toBe('postgres:17');
    });

    it('stack overrides win over registry defaults', function (): void {
        $this->registry->loadForStack($this->stackDir);

        // Load manifest with service_overrides
        $manifest = $this->stackLoader->load($this->stackDir);
        $manifest['service_overrides'] = [
            'databases.postgres' => [
                'variables' => ['POSTGRES_VERSION' => '15'],
            ],
        ];

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
            'dev',
            $manifest,
            $this->stackDir,
        );

        expect($compose['services']['postgres']['image'])->toBe('postgres:15');
    });

    it('environment overrides win over stack overrides', function (): void {
        $this->registry->loadForStack($this->stackDir);

        $manifest = $this->stackLoader->load($this->stackDir);
        $manifest['service_overrides'] = [
            'databases.postgres' => [
                'variables' => ['POSTGRES_VERSION' => '15'],
                'environments' => [
                    'production' => [
                        'variables' => ['POSTGRES_VERSION' => '14'],
                    ],
                ],
            ],
        ];

        $compose = $this->service->build(
            ['databases.postgres'],
            $this->projectConfig,
            'production',
            $manifest,
            $this->stackDir,
        );

        // Environment override (14) wins over stack (15) wins over registry (17)
        expect($compose['services']['postgres']['image'])->toBe('postgres:14');
    });
});
