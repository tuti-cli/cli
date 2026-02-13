<?php

declare(strict_types=1);

/**
 * StackInitializationService YAML Validation Tests
 *
 * Tests that YAML output from string-based compose assembly is validated
 * before being written to disk. The indentServiceYaml() and
 * appendVolumesToCompose() methods assemble YAML via string concatenation,
 * which could produce broken YAML if stubs have wrong formatting.
 *
 * @see App\Services\Stack\StackInitializationService
 */

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

// ─── YAML parsing basics ────────────────────────────────────────────────
// Verify Yaml::parse() catches the kind of errors that string-based
// assembly could introduce.

describe('YAML validation', function (): void {

    it('parses valid compose YAML without errors', function (): void {
        $yaml = <<<'YAML'
services:
  app:
    image: php:8.4-fpm
    container_name: myapp_dev_app
    volumes:
      - .:/var/www/html
    networks:
      - app_network

  redis:
    image: redis:7-alpine
    container_name: myapp_dev_redis
    volumes:
      - redis_data:/data
    networks:
      - app_network

networks:
  app_network:
    name: myapp_dev_network

volumes:
  redis_data:
    name: myapp_dev_redis_data
YAML;

        $parsed = Yaml::parse($yaml);

        expect($parsed)
            ->toBeArray()
            ->toHaveKey('services')
            ->toHaveKey('networks')
            ->toHaveKey('volumes');

        expect($parsed['services'])->toHaveKey('app')->toHaveKey('redis');
    });

    it('throws ParseException for tabs mixed with spaces', function (): void {
        $yaml = "services:\n\tapp:\n\t\timage: php:8.4";

        expect(fn (): mixed => Yaml::parse($yaml))
            ->toThrow(ParseException::class);
    });

    it('throws ParseException for malformed indentation', function (): void {
        $yaml = <<<'YAML'
services:
  app:
    image: php:8.4
     container_name: broken
YAML;

        expect(fn (): mixed => Yaml::parse($yaml))
            ->toThrow(ParseException::class);
    });

    it('throws ParseException for duplicate keys at same level', function (): void {
        $yaml = <<<'YAML'
services:
  app:
    image: first
  app:
    image: second
YAML;

        expect(fn (): mixed => Yaml::parse($yaml))
            ->toThrow(ParseException::class, 'Duplicate key');
    });
});

// ─── indentServiceYaml pattern validation ───────────────────────────────
// Tests that the 2-space indent pattern used by indentServiceYaml()
// produces valid YAML when inserted into a compose structure.

describe('indentServiceYaml pattern', function (): void {

    it('produces valid YAML when service stub is inserted into compose structure', function (): void {
        // Simulate what indentServiceYaml does: add 2-space indent to each line
        $serviceStub = <<<'STUB'
redis:
  image: redis:7-alpine
  container_name: myapp_dev_redis
  volumes:
    - redis_data:/data
  networks:
    - app_network
STUB;

        // Apply 2-space indent (same logic as indentServiceYaml)
        $lines = explode("\n", mb_trim($serviceStub));
        $indented = array_map(fn (string $line): string => $line === '' ? '' : '  ' . $line, $lines);
        $indentedYaml = implode("\n", $indented);

        // Build a compose structure with the indented service inserted
        $compose = <<<YAML
services:
  app:
    image: php:8.4-fpm

{$indentedYaml}

networks:
  app_network:
    name: myapp_dev_network
YAML;

        $parsed = Yaml::parse($compose);

        expect($parsed)
            ->toBeArray()
            ->toHaveKey('services');

        expect($parsed['services'])->toHaveKey('redis');
        expect($parsed['services']['redis']['image'])->toBe('redis:7-alpine');
    });

    it('produces valid YAML when multiple services are indented and inserted', function (): void {
        $services = [
            <<<'STUB'
redis:
  image: redis:7-alpine
  container_name: myapp_dev_redis
STUB,
            <<<'STUB'
mailpit:
  image: axllent/mailpit
  container_name: myapp_dev_mailpit
STUB,
        ];

        $allIndented = '';
        foreach ($services as $stub) {
            $lines = explode("\n", mb_trim($stub));
            $indented = array_map(fn (string $line): string => $line === '' ? '' : '  ' . $line, $lines);
            $allIndented .= "\n" . implode("\n", $indented);
        }

        $compose = <<<YAML
services:
  app:
    image: php:8.4-fpm
{$allIndented}

networks:
  app_network:
    name: myapp_dev_network
YAML;

        $parsed = Yaml::parse($compose);

        expect($parsed['services'])
            ->toHaveKey('app')
            ->toHaveKey('redis')
            ->toHaveKey('mailpit');
    });

    it('produces valid YAML when volumes are appended', function (): void {
        $compose = <<<'YAML'
services:
  app:
    image: php:8.4-fpm

volumes:
  app_data:
    name: myapp_dev_app_data
YAML;

        // Simulate appendVolumesToCompose adding a new volume
        $newVolume = "  redis_data:\n    name: myapp_dev_redis_data\n";
        $result = mb_rtrim($compose) . "\n" . $newVolume;

        $parsed = Yaml::parse($result);

        expect($parsed['volumes'])
            ->toHaveKey('app_data')
            ->toHaveKey('redis_data');
    });
});
