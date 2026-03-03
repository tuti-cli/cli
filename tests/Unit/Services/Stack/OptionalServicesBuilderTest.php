<?php

declare(strict_types=1);

use App\Services\Stack\OptionalServicesBuilder;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackStubLoaderService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-optional-services-test-' . uniqid();
    $this->stackDir = sys_get_temp_dir() . '/tuti-test-stack-' . uniqid();

    mkdir($this->testDir);
    mkdir($this->stackDir);
    mkdir($this->stackDir . '/services');
    mkdir($this->stackDir . '/services/databases');

    // Create registry.json
    $registry = [
        'version' => '1.0.0',
        'services' => [
            'databases' => [
                'postgres' => [
                    'name' => 'PostgreSQL',
                    'stub' => 'databases/postgres.stub',
                    'volumes' => ['postgres_data'],
                ],
                'mysql' => [
                    'name' => 'MySQL',
                    'stub' => 'databases/mysql.stub',
                    'volumes' => ['mysql_data'],
                ],
            ],
            'cache' => [
                'redis' => [
                    'name' => 'Redis',
                    'stub' => 'cache/redis.stub',
                    'volumes' => ['redis_data'],
                ],
            ],
        ],
    ];
    file_put_contents($this->stackDir . '/services/registry.json', json_encode($registry));

    // Create postgres stub
    $postgresStub = <<<'STUB'
# @section: base
postgres:
  image: postgres:16
  environment:
    POSTGRES_DB: {{PROJECT_NAME}}
    POSTGRES_USER: tuti
    POSTGRES_PASSWORD: secret
  volumes:
    - postgres_data:/var/lib/postgresql/data

# @section: dev
postgres:
  ports:
    - "5432:5432"
STUB;
    file_put_contents($this->stackDir . '/services/databases/postgres.stub', $postgresStub);

    // Create mysql stub (no sections - legacy format)
    $mysqlStub = <<<'STUB'
mysql:
  image: mysql:8
  environment:
    MYSQL_DATABASE: {{PROJECT_NAME}}
    MYSQL_USER: tuti
    MYSQL_PASSWORD: secret
    MYSQL_ROOT_PASSWORD: root
  volumes:
    - mysql_data:/var/lib/mysql
STUB;
    file_put_contents($this->stackDir . '/services/databases/mysql.stub', $mysqlStub);

    // Create redis stub
    mkdir($this->stackDir . '/services/cache');
    $redisStub = <<<'STUB'
# @section: base
redis:
  image: redis:7
  command: redis-server --maxmemory {{REDIS_MAX_MEMORY}}

# @section: dev
redis:
  ports:
    - "6379:6379"
STUB;
    file_put_contents($this->stackDir . '/services/cache/redis.stub', $redisStub);

    // Initialize services
    $this->registryManager = new StackRegistryManagerService();
    $this->registryManager->loadForStack($this->stackDir);
    $this->stubLoader = new StackStubLoaderService();

    $this->builder = new OptionalServicesBuilder(
        $this->registryManager,
        $this->stubLoader
    );
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (is_dir($this->stackDir)) {
        File::deleteDirectory($this->stackDir);
    }
});

describe('appendServices', function (): void {
    it('does nothing when compose file does not exist', function (): void {
        $result = $this->builder->appendServices(
            ['databases.postgres'],
            'test-project',
            'dev',
            $this->testDir . '/nonexistent.yml',
            $this->testDir . '/dev.yml'
        );

        expect($result)->toBeNull();
    });

    it('does nothing when no services selected', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $originalContent = "services:\n  app:\n    image: php:8.4\n";
        file_put_contents($composeFile, $originalContent);

        $this->builder->appendServices(
            [],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        expect(file_get_contents($composeFile))->toBe($originalContent);
    });

    it('appends single service to compose file', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['databases.postgres'],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('postgres:')
            ->and($result)->toContain('image: postgres:16')
            ->and($result)->toContain('postgres_data:');
    });

    it('skips service already in compose file', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4
  postgres:
    image: postgres:16

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);
        $originalLength = mb_strlen($composeContent);

        $this->builder->appendServices(
            ['databases.postgres'],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        // Should not add duplicate postgres service - content length should remain similar
        // (may have minor whitespace changes but not entire new service)
        expect(mb_strlen($result))->toBeLessThan($originalLength + 50);
    });

    it('appends multiple services', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['databases.postgres', 'cache.redis'],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('postgres:')
            ->and($result)->toContain('redis:')
            ->and($result)->toContain('postgres_data:')
            ->and($result)->toContain('redis_data:');
    });

    it('applies variable replacements', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['databases.postgres'],
            'my-awesome-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('POSTGRES_DB: my-awesome-project');
    });

    it('applies environment-specific redis memory settings', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['cache.redis'],
            'test-project',
            'staging',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('redis:')
            ->and($result)->toContain('512mb'); // staging = 512mb
    });

    it('adds volumes from service config', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

volumes: {}

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['databases.postgres'],
            'my-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('postgres_data:')
            ->and($result)->toContain('my-project_${APP_ENV:-dev}_postgres_data');
    });

    it('appends dev sections to dev compose file', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $devComposeFile = $this->testDir . '/docker-compose.dev.yml';

        file_put_contents($composeFile, "services:\n  app:\n    image: php:8.4\nnetworks:\n  app_network:\n");
        file_put_contents($devComposeFile, "services:\n  app:\n    image: php:8.4\nnetworks:\n  app_network:\n");

        $this->builder->appendServices(
            ['databases.postgres'],
            'test-project',
            'dev',
            $composeFile,
            $devComposeFile
        );

        $devResult = file_get_contents($devComposeFile);
        expect($devResult)->toContain('5432:5432');
    });

    it('handles legacy stub format without sections', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $composeContent = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;
        file_put_contents($composeFile, $composeContent);

        $this->builder->appendServices(
            ['databases.mysql'],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        $result = file_get_contents($composeFile);
        expect($result)->toContain('mysql:')
            ->and($result)->toContain('image: mysql:8');
    });

    it('skips service not in registry', function (): void {
        $composeFile = $this->testDir . '/docker-compose.yml';
        $originalContent = "services:\n  app:\n    image: php:8.4\nnetworks:\n  app_network:\n";
        file_put_contents($composeFile, $originalContent);

        $this->builder->appendServices(
            ['databases.nonexistent'],
            'test-project',
            'dev',
            $composeFile,
            $this->testDir . '/docker-compose.dev.yml'
        );

        // Should not throw and content should remain unchanged (except possibly trailing whitespace)
        $result = file_get_contents($composeFile);
        expect(mb_trim($result))->toBe(mb_trim($originalContent));
    });
});

describe('buildReplacements', function (): void {
    it('builds correct replacements array', function (): void {
        $replacements = $this->builder->buildReplacements('my-project');

        expect($replacements)
            ->toHaveKey('PROJECT_NAME', 'my-project')
            ->toHaveKey('NETWORK_NAME', 'app_network')
            ->toHaveKey('APP_DOMAIN', 'my-project.local.test');
    });
});

describe('findServicesInsertionPoint', function (): void {
    it('finds insertion point before Networks header', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

# =============================================================================
# Networks
# =============================================================================
networks:
  app_network:

YAML;

        $point = $this->builder->findServicesInsertionPoint($content);

        expect($point)->toBeGreaterThan(0)
            ->and(mb_substr($content, $point, 10))->toContain('#');
    });

    it('finds insertion point before networks key', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

networks:
  app_network:

YAML;

        $point = $this->builder->findServicesInsertionPoint($content);

        expect($point)->toBeGreaterThan(0);
    });

    it('finds insertion point before Volumes header', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

# =============================================================================
# Volumes
# =============================================================================
volumes:
  app_data:

YAML;

        $point = $this->builder->findServicesInsertionPoint($content);

        expect($point)->toBeGreaterThan(0);
    });

    it('finds insertion point before volumes key', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

volumes:
  app_data:

YAML;

        $point = $this->builder->findServicesInsertionPoint($content);

        expect($point)->toBeGreaterThan(0);
    });

    it('returns end of file when no section found', function (): void {
        $content = "services:\n  app:\n    image: php:8.4\n";

        $point = $this->builder->findServicesInsertionPoint($content);

        expect($point)->toBe(mb_strlen($content));
    });
});

describe('appendVolumesToCompose', function (): void {
    it('replaces empty volumes section', function (): void {
        $content = "services:\n  app:\n    image: php:8.4\n\nvolumes: {}\n";

        $result = $this->builder->appendVolumesToCompose($content, ['postgres_data' => 'my-project']);

        expect($result)
            ->toContain('volumes:')
            ->toContain('postgres_data:')
            ->toContain('my-project_${APP_ENV:-dev}_postgres_data')
            ->not->toContain('{}');
    });

    it('appends to existing volumes section', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

volumes:
  app_data:

YAML;

        $result = $this->builder->appendVolumesToCompose($content, ['postgres_data' => 'my-project']);

        expect($result)
            ->toContain('postgres_data:')
            ->toContain('my-project_${APP_ENV:-dev}_postgres_data');
    });

    it('adds volumes section when missing', function (): void {
        $content = "services:\n  app:\n    image: php:8.4\n";

        $result = $this->builder->appendVolumesToCompose($content, ['postgres_data' => 'my-project']);

        expect($result)
            ->toContain('volumes:')
            ->toContain('postgres_data:');
    });

    it('skips already existing volumes', function (): void {
        $content = <<<'YAML'
services:
  app:
    image: php:8.4

volumes:
  postgres_data:
    name: existing_volume

YAML;

        $result = $this->builder->appendVolumesToCompose($content, ['postgres_data' => 'my-project']);

        expect($result)->not->toContain('my-project_${APP_ENV:-dev}_postgres_data');
    });

    it('handles multiple volumes', function (): void {
        $content = "services:\n  app:\n    image: php:8.4\n\nvolumes: {}\n";

        $result = $this->builder->appendVolumesToCompose($content, [
            'postgres_data' => 'my-project',
            'redis_data' => 'my-project',
        ]);

        expect($result)
            ->toContain('postgres_data:')
            ->toContain('redis_data:');
    });
});

describe('indentServiceYaml', function (): void {
    it('indents single line yaml', function (): void {
        $yaml = 'postgres:
  image: postgres:16';

        $result = $this->builder->indentServiceYaml($yaml);

        expect($result)
            ->toBe('  postgres:
    image: postgres:16');
    });

    it('handles empty lines', function (): void {
        $yaml = "postgres:\n  image: postgres:16\n\n  environment:\n    POSTGRES_DB: test";

        $result = $this->builder->indentServiceYaml($yaml);

        expect($result)->toContain("  postgres:\n    image:")
            ->and($result)->toContain("\n\n    environment:");
    });

    it('handles multiline yaml correctly', function (): void {
        $yaml = <<<'YAML'
redis:
  image: redis:7
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
YAML;

        $result = $this->builder->indentServiceYaml($yaml);

        expect($result)
            ->toContain('  redis:')
            ->toContain('    image:')
            ->toContain('    ports:')
            ->toContain('    volumes:');
    });
});
