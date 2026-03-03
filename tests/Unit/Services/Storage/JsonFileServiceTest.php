<?php

declare(strict_types=1);

/**
 * JsonFileService Unit Tests
 *
 * Tests error handling paths and normal operations for the JSON file service.
 *
 * @see JsonFileService
 */

use App\Services\Storage\JsonFileService;

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->service = new JsonFileService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── read() error handling ───────────────────────────────────────────────

describe('read() error handling', function (): void {

    it('throws RuntimeException when file does not exist', function (): void {
        expect(fn () => $this->service->read($this->testDir . '/nonexistent.json'))
            ->toThrow(RuntimeException::class, 'File not found');
    });

    it('throws RuntimeException for invalid JSON syntax', function (): void {
        $filePath = $this->testDir . '/invalid.json';
        file_put_contents($filePath, '{invalid json content');

        expect(fn () => $this->service->read($filePath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });

    it('throws RuntimeException for JSON with trailing comma', function (): void {
        $filePath = $this->testDir . '/trailing-comma.json';
        file_put_contents($filePath, '{"key": "value",}');

        expect(fn () => $this->service->read($filePath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });

    it('throws RuntimeException for empty file', function (): void {
        $filePath = $this->testDir . '/empty.json';
        file_put_contents($filePath, '');

        expect(fn () => $this->service->read($filePath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });

    it('throws RuntimeException for file with only whitespace', function (): void {
        $filePath = $this->testDir . '/whitespace.json';
        file_put_contents($filePath, "   \n\t  ");

        expect(fn () => $this->service->read($filePath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });
});

// ─── read() success paths ─────────────────────────────────────────────────

describe('read() success paths', function (): void {

    it('reads valid JSON object', function (): void {
        $filePath = $this->testDir . '/valid.json';
        file_put_contents($filePath, '{"name": "test", "value": 123}');

        $result = $this->service->read($filePath);

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'test')
            ->toHaveKey('value', 123);
    });

    it('reads JSON with nested structures', function (): void {
        $filePath = $this->testDir . '/nested.json';
        file_put_contents($filePath, json_encode([
            'project' => [
                'name' => 'my-project',
                'services' => ['redis', 'postgres'],
            ],
        ]));

        $result = $this->service->read($filePath);

        expect($result['project']['name'])->toBe('my-project')
            ->and($result['project']['services'])->toBe(['redis', 'postgres']);
    });

    it('resolves template variables when provided', function (): void {
        $filePath = $this->testDir . '/template.json';
        file_put_contents($filePath, '{"path": "{{USER}}/projects", "name": "{{APP_NAME}}"}');

        $result = $this->service->read($filePath, [
            '{{USER}}' => 'john',
            '{{APP_NAME}}' => 'my-app',
        ]);

        expect($result['path'])->toBe('john/projects')
            ->and($result['name'])->toBe('my-app');
    });

    it('reads JSON without variables when none provided', function (): void {
        $filePath = $this->testDir . '/no-vars.json';
        file_put_contents($filePath, '{"path": "{{USER}}/projects"}');

        $result = $this->service->read($filePath);

        expect($result['path'])->toBe('{{USER}}/projects');
    });
});

// ─── write() error handling ───────────────────────────────────────────────

describe('write() error handling', function (): void {

    it('throws RuntimeException for data that cannot be JSON encoded', function (): void {
        // Create a resource that can't be JSON encoded
        $resource = fopen('php://memory', 'r');
        $data = ['resource' => $resource];

        expect(fn () => $this->service->write($this->testDir . '/test.json', $data))
            ->toThrow(RuntimeException::class, 'Failed to encode JSON');

        fclose($resource);
    });

    it('throws RuntimeException for circular reference', function (): void {
        $data = ['key' => 'value'];
        $data['circular'] = &$data; // Circular reference

        expect(fn () => $this->service->write($this->testDir . '/circular.json', $data))
            ->toThrow(RuntimeException::class, 'Failed to encode JSON');
    });
});

// ─── write() success paths ────────────────────────────────────────────────

describe('write() success paths', function (): void {

    it('writes valid JSON to file', function (): void {
        $filePath = $this->testDir . '/output.json';
        $data = ['name' => 'test', 'version' => '1.0.0'];

        $this->service->write($filePath, $data);

        expect(file_exists($filePath))->toBeTrue();
        $content = file_get_contents($filePath);
        expect(json_decode($content, true))->toBe($data);
    });

    it('creates parent directories if they do not exist', function (): void {
        $filePath = $this->testDir . '/nested/deep/dir/output.json';
        $data = ['created' => true];

        $this->service->write($filePath, $data);

        expect(file_exists($filePath))->toBeTrue()
            ->and(json_decode(file_get_contents($filePath), true))->toBe($data);
    });

    it('overwrites existing file', function (): void {
        $filePath = $this->testDir . '/overwrite.json';
        file_put_contents($filePath, '{"old": "data"}');

        $this->service->write($filePath, ['new' => 'data']);

        $result = json_decode(file_get_contents($filePath), true);
        expect($result)->toBe(['new' => 'data']);
    });

    it('writes JSON with pretty print formatting', function (): void {
        $filePath = $this->testDir . '/pretty.json';
        $data = ['key1' => 'value1', 'key2' => 'value2'];

        $this->service->write($filePath, $data);

        $content = file_get_contents($filePath);
        expect($content)->toContain("\n")  // Pretty print has newlines
            ->and($content)->toContain('    ');  // And indentation
    });

    it('handles empty array', function (): void {
        $filePath = $this->testDir . '/empty.json';

        $this->service->write($filePath, []);

        $result = json_decode(file_get_contents($filePath), true);
        expect($result)->toBe([]);
    });

    it('handles special characters in data', function (): void {
        $filePath = $this->testDir . '/special.json';
        $data = [
            'path' => '/var/www/html',
            'unicode' => '日本語',
            'quotes' => 'He said "hello"',
        ];

        $this->service->write($filePath, $data);

        $result = json_decode(file_get_contents($filePath), true);
        expect($result)->toBe($data);
    });
});

// ─── exists() ─────────────────────────────────────────────────────────────

describe('exists()', function (): void {

    it('returns true for existing file', function (): void {
        $filePath = $this->testDir . '/exists.json';
        file_put_contents($filePath, '{}');

        expect($this->service->exists($filePath))->toBeTrue();
    });

    it('returns false for non-existent file', function (): void {
        expect($this->service->exists($this->testDir . '/missing.json'))->toBeFalse();
    });

    it('returns true for directory path (File::exists checks existence)', function (): void {
        // File::exists() returns true for both files and directories
        expect($this->service->exists($this->testDir))->toBeTrue();
    });
});

// ─── delete() ─────────────────────────────────────────────────────────────

describe('delete()', function (): void {

    it('deletes existing file', function (): void {
        $filePath = $this->testDir . '/to-delete.json';
        file_put_contents($filePath, '{}');

        expect($this->service->delete($filePath))->toBeTrue()
            ->and(file_exists($filePath))->toBeFalse();
    });

    it('returns false for non-existent file', function (): void {
        expect($this->service->delete($this->testDir . '/missing.json'))->toBeFalse();
    });
});

// ─── atomic write verification ─────────────────────────────────────────────

describe('atomic write verification', function (): void {

    it('does not leave temp files on success', function (): void {
        $filePath = $this->testDir . '/atomic.json';

        $this->service->write($filePath, ['test' => 'data']);

        $files = glob($this->testDir . '/json-*');
        expect($files)->toBeEmpty();
    });

    it('uses temp file during write process', function (): void {
        $filePath = $this->testDir . '/temp-check.json';

        // The temp file should be cleaned up after write
        $this->service->write($filePath, ['data' => 'value']);

        // Verify only the target file exists (not temp files)
        $files = scandir($this->testDir);
        $jsonFiles = array_values(array_filter($files, fn ($f): bool => str_ends_with($f, '.json')));
        expect($jsonFiles)->toBe(['temp-check.json']);
    });
});
