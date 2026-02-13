<?php

declare(strict_types=1);

/**
 * StackStubLoaderService Unit Tests
 *
 * Tests the stub file loading, section parsing, and placeholder replacement.
 * This service is the foundation of Docker Compose generation - it reads
 * service stubs (like postgres.stub, redis.stub) and splits them into
 * sections (base, dev, volumes, env) while replacing {{PLACEHOLDER}} values.
 *
 * @see StackStubLoaderService
 */

use App\Services\Stack\StackStubLoaderService;
use Illuminate\Support\Facades\File;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We create real temporary files for each test. This is better than mocking
// the File facade because we test actual file I/O behavior - and it's fast
// since temp files are tiny.

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->service = new StackStubLoaderService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── Helper: create a stub file in our temp directory ────────────────────

function createStubFile(string $dir, string $filename, string $content): string
{
    $path = $dir . '/' . $filename;
    $directory = dirname($path);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, $content);

    return $path;
}

// ─── parseSections() ────────────────────────────────────────────────────
// This is the core parsing logic. It splits stub content by "# @section:"
// markers into a key=>value array like ['base' => '...', 'dev' => '...']

describe('parseSections', function (): void {

    it('parses content with multiple sections', function (): void {
        // This is what a real stub looks like - sections separated by markers
        $content = <<<'STUB'
# @section: base
  postgres:
    image: postgres:17
    ports:
      - "${DB_PORT:-5432}:5432"
# @section: dev
  postgres:
    volumes:
      - postgres_data:/var/lib/postgresql/data
# @section: volumes
  postgres_data:
    driver: local
# @section: env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
STUB;

        $sections = $this->service->parseSections($content);

        // Should have all 4 sections
        expect($sections)
            ->toBeArray()
            ->toHaveCount(4)
            ->toHaveKeys(['base', 'dev', 'volumes', 'env']);

        // Each section should contain its content (trimmed)
        expect($sections['base'])->toContain('postgres:');
        expect($sections['base'])->toContain('image: postgres:17');
        expect($sections['dev'])->toContain('postgres_data:/var/lib/postgresql/data');
        expect($sections['volumes'])->toContain('driver: local');
        expect($sections['env'])->toContain('DB_CONNECTION=pgsql');
    });

    it('defaults to base section when no markers exist', function (): void {
        // A stub without any # @section: markers - everything goes into 'base'
        $content = <<<'STUB'
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
STUB;

        $sections = $this->service->parseSections($content);

        expect($sections)
            ->toHaveCount(1)
            ->toHaveKey('base');

        expect($sections['base'])->toContain('redis:');
    });

    it('returns base section with empty content for empty string', function (): void {
        // Even an empty string defaults to 'base' section because
        // explode("\n", '') returns [''] - one empty line element.
        // This is correct behavior: no markers = everything is 'base'.
        $sections = $this->service->parseSections('');

        expect($sections)
            ->toHaveCount(1)
            ->toHaveKey('base');

        expect($sections['base'])->toBe('');
    });

    it('handles content with only section markers and no content', function (): void {
        $content = "# @section: base\n# @section: dev";

        $sections = $this->service->parseSections($content);

        // 'base' section is empty (nothing between markers), so not saved
        // 'dev' section is also empty
        expect($sections)->toBeEmpty();
    });

    it('normalizes section names to lowercase', function (): void {
        $content = <<<'STUB'
# @section: BASE
some_content: here
# @section: Dev
other_content: there
STUB;

        $sections = $this->service->parseSections($content);

        expect($sections)
            ->toHaveKey('base')
            ->toHaveKey('dev')
            ->not->toHaveKey('BASE')
            ->not->toHaveKey('Dev');
    });

    it('trims whitespace from section content', function (): void {
        $content = <<<'STUB'
# @section: base

  service:
    image: test

# @section: dev

  service:
    volumes:
      - data:/var/data

STUB;

        $sections = $this->service->parseSections($content);

        // Content should be trimmed - no leading/trailing blank lines
        expect($sections['base'])->not->toStartWith("\n");
        expect($sections['base'])->not->toEndWith("\n");
        expect($sections['dev'])->not->toStartWith("\n");
    });

    it('handles extra spaces around section marker', function (): void {
        $content = "#   @section:   volumes  \nvolume_data:\n  driver: local";

        $sections = $this->service->parseSections($content);

        expect($sections)->toHaveKey('volumes');
        expect($sections['volumes'])->toContain('driver: local');
    });
});

// ─── load() ─────────────────────────────────────────────────────────────
// Loads a stub file from disk and replaces {{PLACEHOLDER}} values.
// This is used when building Docker Compose files.

describe('load', function (): void {

    it('loads a stub file and returns its content', function (): void {
        $path = createStubFile($this->testDir, 'test.stub', 'image: postgres:17');

        $content = $this->service->load($path);

        expect($content)->toBe('image: postgres:17');
    });

    it('replaces placeholders with provided values', function (): void {
        $stubContent = <<<'STUB'
  {{SERVICE_NAME}}:
    image: {{IMAGE_NAME}}:{{IMAGE_TAG}}
    container_name: {{PROJECT_NAME}}_{{SERVICE_NAME}}
STUB;

        $path = createStubFile($this->testDir, 'service.stub', $stubContent);

        $content = $this->service->load($path, [
            'SERVICE_NAME' => 'postgres',
            'IMAGE_NAME' => 'postgres',
            'IMAGE_TAG' => '17',
            'PROJECT_NAME' => 'my-app',
        ]);

        expect($content)
            ->toContain('postgres:')
            ->toContain('image: postgres:17')
            ->toContain('container_name: my-app_postgres')
            ->not->toContain('{{');
    });

    it('leaves unreplaced placeholders as-is when no replacement provided', function (): void {
        $path = createStubFile($this->testDir, 'partial.stub', 'host: {{DB_HOST}} port: {{DB_PORT}}');

        // Only replace DB_HOST, leave DB_PORT as placeholder
        $content = $this->service->load($path, ['DB_HOST' => 'localhost']);

        expect($content)
            ->toContain('host: localhost')
            ->toContain('port: {{DB_PORT}}');
    });

    it('throws RuntimeException when stub file does not exist', function (): void {
        expect(fn () => $this->service->load('/nonexistent/path/missing.stub'))
            ->toThrow(RuntimeException::class, 'Stub file not found');
    });

    it('handles empty replacements array', function (): void {
        $path = createStubFile($this->testDir, 'no-replace.stub', 'static content here');

        $content = $this->service->load($path, []);

        expect($content)->toBe('static content here');
    });

    it('handles stub file with empty content', function (): void {
        $path = createStubFile($this->testDir, 'empty.stub', '');

        $content = $this->service->load($path);

        expect($content)->toBe('');
    });
});

// ─── loadSection() ──────────────────────────────────────────────────────
// Loads just ONE section from a multi-section stub. For example,
// loading only the 'volumes' section from postgres.stub.

describe('loadSection', function (): void {

    it('loads a specific section from a multi-section stub', function (): void {
        $stubContent = <<<'STUB'
# @section: base
  postgres:
    image: postgres:17
# @section: volumes
  postgres_data:
    driver: local
# @section: env
DB_PORT=5432
STUB;

        $path = createStubFile($this->testDir, 'sections.stub', $stubContent);

        $base = $this->service->loadSection($path, 'base');
        $volumes = $this->service->loadSection($path, 'volumes');
        $env = $this->service->loadSection($path, 'env');

        expect($base)->toContain('image: postgres:17');
        expect($volumes)->toContain('driver: local');
        expect($env)->toContain('DB_PORT=5432');
    });

    it('returns null for nonexistent section', function (): void {
        $path = createStubFile($this->testDir, 'base-only.stub', "# @section: base\ncontent here");

        $result = $this->service->loadSection($path, 'nonexistent');

        expect($result)->toBeNull();
    });

    it('replaces placeholders within the loaded section', function (): void {
        $stubContent = "# @section: env\nDB_HOST={{DB_HOST}}\nDB_PORT={{DB_PORT}}";

        $path = createStubFile($this->testDir, 'env.stub', $stubContent);

        $result = $this->service->loadSection($path, 'env', [
            'DB_HOST' => 'postgres',
            'DB_PORT' => '5432',
        ]);

        expect($result)
            ->toContain('DB_HOST=postgres')
            ->toContain('DB_PORT=5432');
    });

    it('throws RuntimeException when stub file does not exist', function (): void {
        expect(fn () => $this->service->loadSection('/missing.stub', 'base'))
            ->toThrow(RuntimeException::class, 'Stub file not found');
    });
});

// ─── hasSections() ──────────────────────────────────────────────────────
// Quick check: does this stub use section markers?
// Used to decide whether to parse sections or treat the whole file as one block.

describe('hasSections', function (): void {

    it('returns true when stub has section markers', function (): void {
        $path = createStubFile($this->testDir, 'with-sections.stub', "# @section: base\ncontent");

        expect($this->service->hasSections($path))->toBeTrue();
    });

    it('returns false when stub has no section markers', function (): void {
        $path = createStubFile($this->testDir, 'no-sections.stub', "just plain content\nno markers");

        expect($this->service->hasSections($path))->toBeFalse();
    });

    it('returns false when stub file does not exist', function (): void {
        expect($this->service->hasSections('/nonexistent/file.stub'))->toBeFalse();
    });
});

// ─── getSectionNames() ──────────────────────────────────────────────────
// Returns array of section names found in a stub, e.g. ['base', 'dev', 'volumes']

describe('getSectionNames', function (): void {

    it('returns all section names from a stub', function (): void {
        $stubContent = "# @section: base\ncontent\n# @section: dev\ncontent\n# @section: volumes\ncontent";
        $path = createStubFile($this->testDir, 'named.stub', $stubContent);

        $names = $this->service->getSectionNames($path);

        expect($names)
            ->toBeArray()
            ->toContain('base')
            ->toContain('dev')
            ->toContain('volumes');
    });

    it('returns empty array when file does not exist', function (): void {
        expect($this->service->getSectionNames('/nonexistent.stub'))
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns base key for content without section markers', function (): void {
        $path = createStubFile($this->testDir, 'plain.stub', 'just content');

        $names = $this->service->getSectionNames($path);

        expect($names)->toBe(['base']);
    });
});

// ─── getUnreplacedPlaceholders() ────────────────────────────────────────
// After replacing values, this checks if any {{PLACEHOLDER}} was missed.
// Useful for validation: warn the user if generated config has placeholders.

describe('getUnreplacedPlaceholders', function (): void {

    it('finds unreplaced placeholders in content', function (): void {
        $content = 'host: {{DB_HOST}} port: {{DB_PORT}} name: {{DB_NAME}}';

        $unreplaced = $this->service->getUnreplacedPlaceholders($content);

        expect($unreplaced)
            ->toBeArray()
            ->toHaveCount(3)
            ->toContain('DB_HOST')
            ->toContain('DB_PORT')
            ->toContain('DB_NAME');
    });

    it('returns empty array when no placeholders exist', function (): void {
        $content = 'host: localhost port: 5432';

        expect($this->service->getUnreplacedPlaceholders($content))
            ->toBeArray()
            ->toBeEmpty();
    });

    it('does not match single-brace variables like ${VAR}', function (): void {
        // Docker Compose uses ${VAR:-default} syntax - these are NOT our placeholders
        $content = 'port: ${DB_PORT:-5432}';

        expect($this->service->getUnreplacedPlaceholders($content))
            ->toBeEmpty();
    });

    it('handles mixed replaced and unreplaced content', function (): void {
        $content = 'host: localhost port: {{DB_PORT}}';

        $unreplaced = $this->service->getUnreplacedPlaceholders($content);

        expect($unreplaced)
            ->toHaveCount(1)
            ->toContain('DB_PORT');
    });
});

// ─── loadMultiple() ─────────────────────────────────────────────────────
// Combines multiple stubs into one string. Used when building compose files
// that need content from several service stubs (postgres + redis + etc).

describe('loadMultiple', function (): void {

    it('loads and combines multiple stub files', function (): void {
        $path1 = createStubFile($this->testDir, 'first.stub', 'service_one: true');
        $path2 = createStubFile($this->testDir, 'second.stub', 'service_two: true');

        $combined = $this->service->loadMultiple([
            ['path' => $path1],
            ['path' => $path2],
        ]);

        expect($combined)
            ->toContain('service_one: true')
            ->toContain('service_two: true');
    });

    it('applies replacements per stub', function (): void {
        $path1 = createStubFile($this->testDir, 'a.stub', 'name: {{NAME}}');
        $path2 = createStubFile($this->testDir, 'b.stub', 'port: {{PORT}}');

        $combined = $this->service->loadMultiple([
            ['path' => $path1, 'replacements' => ['NAME' => 'postgres']],
            ['path' => $path2, 'replacements' => ['PORT' => '5432']],
        ]);

        expect($combined)
            ->toContain('name: postgres')
            ->toContain('port: 5432');
    });

    it('handles empty stubs array', function (): void {
        $combined = $this->service->loadMultiple([]);

        expect($combined)->toBe('');
    });
});
