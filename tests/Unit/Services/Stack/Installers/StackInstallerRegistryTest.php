<?php

declare(strict_types=1);

/**
 * StackInstallerRegistry Unit Tests
 *
 * Tests error handling paths and normal operations for the stack installer registry.
 *
 * @see StackInstallerRegistry
 */

use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackInstallerRegistry;

// ─── Test Helpers ─────────────────────────────────────────────────────────

function createMockInstaller(
    string $identifier,
    string $name,
    string $description = '',
    string $framework = 'generic',
    array $supports = [],
): StackInstallerInterface {
    return new readonly class($identifier, $name, $description, $framework, $supports) implements StackInstallerInterface
    {
        public function __construct(
            private string $identifier,
            private string $name,
            private string $description,
            private string $framework,
            private array $supports,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getDescription(): string
        {
            return $this->description;
        }

        public function getFramework(): string
        {
            return $this->framework;
        }

        public function supports(string $stack): bool
        {
            return in_array($stack, $this->supports, true) || $stack === $this->identifier;
        }

        public function installFresh(string $path, string $name, array $options = []): bool
        {
            return true;
        }

        public function applyToExisting(string $path, array $options = []): bool
        {
            return true;
        }

        public function detectExistingProject(string $path): bool
        {
            return false;
        }

        public function getStackPath(): string
        {
            return '/fake/path/' . $this->identifier;
        }

        public function getAvailableModes(): array
        {
            return ['fresh' => 'Fresh installation', 'existing' => 'Existing project'];
        }
    };
}

// ─── Setup ────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->registry = new StackInstallerRegistry;
});

// ─── get() error handling ─────────────────────────────────────────────────

describe('get() error handling', function (): void {

    it('throws InvalidArgumentException for non-existent installer', function (): void {
        expect(fn () => $this->registry->get('nonexistent'))
            ->toThrow(InvalidArgumentException::class, 'Stack installer not found: nonexistent');
    });

    it('throws InvalidArgumentException with exact identifier in message', function (): void {
        expect(fn () => $this->registry->get('laravel-custom'))
            ->toThrow(InvalidArgumentException::class, 'Stack installer not found: laravel-custom');
    });

    it('throws InvalidArgumentException when registry is empty', function (): void {
        $emptyRegistry = new StackInstallerRegistry;

        expect(fn (): StackInstallerInterface => $emptyRegistry->get('any-stack'))
            ->toThrow(InvalidArgumentException::class, 'Stack installer not found: any-stack');
    });

    it('throws for partial identifier match', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        // Should not find 'lara' when only 'laravel' exists
        expect(fn () => $this->registry->get('lara'))
            ->toThrow(InvalidArgumentException::class, 'Stack installer not found: lara');
    });

    it('throws for case-sensitive mismatch', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        expect(fn () => $this->registry->get('Laravel'))
            ->toThrow(InvalidArgumentException::class, 'Stack installer not found: Laravel');
    });
});

// ─── get() success paths ──────────────────────────────────────────────────

describe('get() success paths', function (): void {

    it('returns installer by exact identifier', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        $result = $this->registry->get('laravel');

        expect($result)->toBe($installer);
    });

    it('returns installer via supports() method', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel', '', 'laravel', ['lara', 'laravel-zero']);
        $this->registry->register($installer);

        expect($this->registry->get('lara'))->toBe($installer)
            ->and($this->registry->get('laravel-zero'))->toBe($installer);
    });

    it('prefers exact match over supports() match', function (): void {
        $installer1 = createMockInstaller('laravel', 'Laravel', '', 'laravel', ['lara']);
        $installer2 = createMockInstaller('lara', 'Lara');
        $this->registry->register($installer1);
        $this->registry->register($installer2);

        expect($this->registry->get('lara'))->toBe($installer2);
    });
});

// ─── has() ────────────────────────────────────────────────────────────────

describe('has()', function (): void {

    it('returns false for non-existent installer', function (): void {
        expect($this->registry->has('nonexistent'))->toBeFalse();
    });

    it('returns true for registered installer', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        expect($this->registry->has('laravel'))->toBeTrue();
    });

    it('returns true for installer via supports()', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel', '', 'laravel', ['lara']);
        $this->registry->register($installer);

        expect($this->registry->has('lara'))->toBeTrue();
    });

    it('returns false for empty registry', function (): void {
        $emptyRegistry = new StackInstallerRegistry;

        expect($emptyRegistry->has('any'))->toBeFalse();
    });
});

// ─── register() ───────────────────────────────────────────────────────────

describe('register()', function (): void {

    it('registers single installer', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        expect($this->registry->has('laravel'))->toBeTrue()
            ->and($this->registry->get('laravel'))->toBe($installer);
    });

    it('registers multiple installers', function (): void {
        $laravel = createMockInstaller('laravel', 'Laravel');
        $wordpress = createMockInstaller('wordpress', 'WordPress');
        $this->registry->register($laravel);
        $this->registry->register($wordpress);

        expect($this->registry->has('laravel'))->toBeTrue()
            ->and($this->registry->has('wordpress'))->toBeTrue();
    });

    it('overwrites installer with same identifier', function (): void {
        $installer1 = createMockInstaller('laravel', 'Laravel v1');
        $installer2 = createMockInstaller('laravel', 'Laravel v2');
        $this->registry->register($installer1);
        $this->registry->register($installer2);

        expect($this->registry->get('laravel')->getName())->toBe('Laravel v2');
    });
});

// ─── all() ────────────────────────────────────────────────────────────────

describe('all()', function (): void {

    it('returns empty array for empty registry', function (): void {
        expect($this->registry->all())->toBe([]);
    });

    it('returns all registered installers', function (): void {
        $laravel = createMockInstaller('laravel', 'Laravel');
        $wordpress = createMockInstaller('wordpress', 'WordPress');
        $this->registry->register($laravel);
        $this->registry->register($wordpress);

        $all = $this->registry->all();

        expect($all)
            ->toHaveCount(2)
            ->toHaveKey('laravel')
            ->toHaveKey('wordpress');
    });
});

// ─── getAvailableStacks() ─────────────────────────────────────────────────

describe('getAvailableStacks()', function (): void {

    it('returns empty array for empty registry', function (): void {
        expect($this->registry->getAvailableStacks())->toBe([]);
    });

    it('returns formatted stack information', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel', 'A PHP framework', 'laravel');
        $this->registry->register($installer);

        $stacks = $this->registry->getAvailableStacks();

        expect($stacks)->toHaveKey('laravel')
            ->and($stacks['laravel'])->toBe([
                'name' => 'Laravel',
                'description' => 'A PHP framework',
                'framework' => 'laravel',
            ]);
    });

    it('includes all registered stacks', function (): void {
        $laravel = createMockInstaller('laravel', 'Laravel', 'PHP framework', 'laravel');
        $wordpress = createMockInstaller('wordpress', 'WordPress', 'CMS', 'wordpress');
        $this->registry->register($laravel);
        $this->registry->register($wordpress);

        $stacks = $this->registry->getAvailableStacks();

        expect($stacks)->toHaveCount(2)
            ->and($stacks)->toHaveKey('laravel')
            ->and($stacks)->toHaveKey('wordpress');
    });
});

// ─── detectForProject() ───────────────────────────────────────────────────

describe('detectForProject()', function (): void {

    it('returns null when no installer detects project', function (): void {
        $installer = createMockInstaller('laravel', 'Laravel');
        $this->registry->register($installer);

        $result = $this->registry->detectForProject('/tmp/nonexistent');

        expect($result)->toBeNull();
    });

    it('returns null for empty registry', function (): void {
        $result = $this->registry->detectForProject('/any/path');

        expect($result)->toBeNull();
    });

    it('returns detecting installer', function (): void {
        $detectingInstaller = new class implements StackInstallerInterface
        {
            public function getIdentifier(): string
            {
                return 'detectable';
            }

            public function getName(): string
            {
                return 'Detectable';
            }

            public function getDescription(): string
            {
                return '';
            }

            public function getFramework(): string
            {
                return 'generic';
            }

            public function supports(string $stack): bool
            {
                return false;
            }

            public function installFresh(string $path, string $name, array $options = []): bool
            {
                return true;
            }

            public function applyToExisting(string $path, array $options = []): bool
            {
                return true;
            }

            public function detectExistingProject(string $path): bool
            {
                return str_contains($path, 'detectable');
            }

            public function getStackPath(): string
            {
                return '/fake/path/detectable';
            }

            public function getAvailableModes(): array
            {
                return ['fresh' => 'Fresh installation'];
            }
        };

        $this->registry->register($detectingInstaller);

        $result = $this->registry->detectForProject('/path/to/detectable-project');

        expect($result)->toBe($detectingInstaller);
    });
});
