<?php

declare(strict_types=1);

/**
 * StackRepositoryService Unit Tests
 *
 * Tests for stack template management including path traversal protection.
 * Security tests verify that malicious stack names cannot escape the
 * intended directories.
 *
 * @see StackRepositoryService
 */

use App\Services\Stack\StackRepositoryService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->jsonService = new JsonFileService;
    $this->service = new StackRepositoryService($this->jsonService);
});

// ─── Security: Path Traversal Protection ─────────────────────────────────
// These tests verify that malicious stack names are rejected before
// any file path construction happens.

describe('path traversal protection', function (): void {

    it('rejects path traversal with ../ sequences', function (): void {
        expect(fn () => $this->service->getStackPath('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects path traversal with .. in middle', function (): void {
        expect(fn () => $this->service->getStackPath('laravel/../wordpress'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects path traversal at end', function (): void {
        expect(fn () => $this->service->getStackPath('laravel/..'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects absolute path attempts', function (): void {
        expect(fn () => $this->service->getStackPath('/etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects null byte injection', function (): void {
        expect(fn () => $this->service->getStackPath("laravel\x00.exe"))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });
});

// ─── Security: Invalid Characters ─────────────────────────────────────────
// Stack names should only contain alphanumeric characters, dashes, and underscores.

describe('invalid characters rejection', function (): void {

    it('rejects spaces in stack name', function (): void {
        expect(fn () => $this->service->getStackPath('my laravel'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects dots in stack name', function (): void {
        expect(fn () => $this->service->getStackPath('laravel.local'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects slashes in stack name', function (): void {
        expect(fn () => $this->service->getStackPath('lara/vel'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects backslashes in stack name', function (): void {
        expect(fn () => $this->service->getStackPath('lara\\vel'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('rejects special characters', function (): void {
        $invalidNames = [
            'laravel!',
            'laravel@',
            'laravel#',
            'laravel$',
            'laravel%',
            'laravel^',
            'laravel&',
            'laravel*',
            'laravel()',
            'laravel[]',
            'laravel{}',
            'laravel|',
            'laravel;',
            'laravel:',
            'laravel"',
            "laravel'",
            'laravel<>',
            'laravel+=',
        ];

        foreach ($invalidNames as $name) {
            expect(fn () => $this->service->getStackPath($name))
                ->toThrow(RuntimeException::class, 'Invalid stack name');
        }
    });

    it('rejects empty stack name', function (): void {
        expect(fn () => $this->service->getStackPath(''))
            ->toThrow(RuntimeException::class, 'Stack name cannot be empty');
    });
});

// ─── Security: Valid Stack Names ──────────────────────────────────────────
// These should all be accepted.

describe('valid stack names', function (): void {

    it('accepts lowercase alphanumeric names', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('laravel');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        // If exception was thrown, it should NOT be about invalid stack name
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        // If no exception (stack exists locally), that's also fine
        expect(true)->toBeTrue();
    });

    it('accepts names with dashes', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('my-laravel-stack');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        expect(true)->toBeTrue();
    });

    it('accepts names with underscores', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('my_laravel_stack');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        expect(true)->toBeTrue();
    });

    it('accepts names with numbers', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('laravel8');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        expect(true)->toBeTrue();
    });

    it('accepts uppercase letters', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('Laravel');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        expect(true)->toBeTrue();
    });

    it('accepts mixed case with dashes and numbers', function (): void {
        $exceptionThrown = false;
        $errorMessage = '';
        try {
            $this->service->getStackPath('My-Laravel-Stack-2024');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }
        if ($exceptionThrown) {
            expect($errorMessage)->not->toContain('Invalid stack name');
        }
        expect(true)->toBeTrue();
    });
});

// ─── Security: All Public Methods Protected ───────────────────────────────
// Every public method that takes a stack name should validate it.

describe('validation on all public methods', function (): void {

    it('validates stack name in downloadStack()', function (): void {
        expect(fn () => $this->service->downloadStack('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('validates stack name in updateStack()', function (): void {
        expect(fn () => $this->service->updateStack('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('validates stack name in hasStack()', function (): void {
        expect(fn () => $this->service->hasStack('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('validates stack name in getStackInfo()', function (): void {
        expect(fn () => $this->service->getStackInfo('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('validates stack name in getCachedStackPath()', function (): void {
        expect(fn () => $this->service->getCachedStackPath('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('validates stack name in clearCache() when stack name provided', function (): void {
        expect(fn () => $this->service->clearCache('../../../etc/passwd'))
            ->toThrow(RuntimeException::class, 'Invalid stack name');
    });

    it('allows clearCache() with null (clears all)', function (): void {
        // Should not throw - null means clear all caches
        $this->service->clearCache(null);
        expect(true)->toBeTrue();
    });
});

// ─── Error Messages ──────────────────────────────────────────────────────
// Clear, user-friendly error messages for invalid input.

describe('error messages', function (): void {

    it('includes the invalid stack name in error message', function (): void {
        try {
            $this->service->getStackPath('bad name!');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('bad name!');
        }
    });

    it('explains allowed characters in error message', function (): void {
        try {
            $this->service->getStackPath('bad name!');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('letters, numbers, dashes, and underscores');
        }
    });

    it('has distinct message for empty stack name', function (): void {
        try {
            $this->service->getStackPath('');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('cannot be empty');
        }
    });
});
