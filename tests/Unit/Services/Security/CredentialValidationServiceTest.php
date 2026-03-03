<?php

declare(strict_types=1);

/**
 * CredentialValidationService Unit Tests
 *
 * Tests the security service that detects common development credentials.
 * Helps prevent accidental deployment of insecure credentials to production.
 *
 * @see CredentialValidationService
 */

use App\Services\Security\CredentialValidationService;

beforeEach(function (): void {
    $this->service = new CredentialValidationService;
});

// ─── isInsecurePassword() ────────────────────────────────────────────────
// Detects common development password patterns

describe('isInsecurePassword', function (): void {

    it('detects common insecure passwords', function (string $password): void {
        expect($this->service->isInsecurePassword($password))->toBeTrue();
    })->with([
        'admin',
        'password',
        'secret',
        'changeme',
        '123456',
        'test',
        'demo',
        'dev',
        'development',
    ]);

    it('is case insensitive', function (): void {
        expect($this->service->isInsecurePassword('ADMIN'))->toBeTrue();
        expect($this->service->isInsecurePassword('Password'))->toBeTrue();
        expect($this->service->isInsecurePassword('SECRET'))->toBeTrue();
    });

    it('trims whitespace before checking', function (): void {
        expect($this->service->isInsecurePassword('  admin  '))->toBeTrue();
        expect($this->service->isInsecurePassword("\tsecret\n"))->toBeTrue();
    });

    it('returns false for secure passwords', function (): void {
        expect($this->service->isInsecurePassword('mySecureP@ssw0rd!'))->toBeFalse();
        expect($this->service->isInsecurePassword('correct-horse-battery-staple'))->toBeFalse();
        expect($this->service->isInsecurePassword('xK9#mP2$vL5@'))->toBeFalse();
    });

    it('returns false for random strings', function (): void {
        expect($this->service->isInsecurePassword(bin2hex(random_bytes(16))))->toBeFalse();
    });
});

// ─── isInsecureUsername() ────────────────────────────────────────────────
// Detects common development username patterns

describe('isInsecureUsername', function (): void {

    it('detects common insecure usernames', function (string $username): void {
        expect($this->service->isInsecureUsername($username))->toBeTrue();
    })->with([
        'admin',
        'root',
        'test',
        'demo',
        'dev',
        'user',
    ]);

    it('is case insensitive', function (): void {
        expect($this->service->isInsecureUsername('ADMIN'))->toBeTrue();
        expect($this->service->isInsecureUsername('Root'))->toBeTrue();
    });

    it('returns false for secure usernames', function (): void {
        expect($this->service->isInsecureUsername('john.doe'))->toBeFalse();
        expect($this->service->isInsecureUsername('user_12345'))->toBeFalse();
        expect($this->service->isInsecureUsername('developer@company'))->toBeFalse();
    });
});

// ─── isInsecureCredentialPair() ──────────────────────────────────────────
// Detects when username/password combination is insecure

describe('isInsecureCredentialPair', function (): void {

    it('detects when username equals password', function (): void {
        expect($this->service->isInsecureCredentialPair('admin', 'admin'))->toBeTrue();
        expect($this->service->isInsecureCredentialPair('john', 'john'))->toBeTrue();
        expect($this->service->isInsecureCredentialPair('anything', 'anything'))->toBeTrue();
    });

    it('detects insecure password with insecure username', function (): void {
        expect($this->service->isInsecureCredentialPair('admin', 'password'))->toBeTrue();
        expect($this->service->isInsecureCredentialPair('root', 'secret'))->toBeTrue();
    });

    it('is case insensitive for comparison', function (): void {
        expect($this->service->isInsecureCredentialPair('Admin', 'ADMIN'))->toBeTrue();
        expect($this->service->isInsecureCredentialPair('admin', 'ADMIN'))->toBeTrue();
    });

    it('returns false for secure credential pairs', function (): void {
        expect($this->service->isInsecureCredentialPair('admin', 'xK9#mP2$vL5@'))->toBeFalse();
        expect($this->service->isInsecureCredentialPair('john.doe', 'correct-horse-battery-staple'))->toBeFalse();
    });
});

// ─── validateCredentials() ───────────────────────────────────────────────
// Validates credential array and returns detailed findings

describe('validateCredentials', function (): void {

    it('detects insecure password fields', function (): void {
        $result = $this->service->validateCredentials([
            'admin_password' => 'admin',
        ]);

        expect($result['has_issues'])->toBeTrue();
        expect($result['issues'])->toHaveCount(1);
        expect($result['issues'][0]['field'])->toBe('admin_password');
        expect($result['issues'][0]['severity'])->toBe('warning');
    });

    it('detects insecure username fields', function (): void {
        $result = $this->service->validateCredentials([
            'admin_user' => 'admin',
        ]);

        expect($result['has_issues'])->toBeTrue();
        expect($result['issues'])->toHaveCount(1);
        expect($result['issues'][0]['message'])->toContain('username');
    });

    it('detects insecure secret/key fields', function (): void {
        $result = $this->service->validateCredentials([
            'api_key' => 'secret',
            'token' => 'changeme',
        ]);

        expect($result['has_issues'])->toBeTrue();
        expect($result['issues'])->toHaveCount(2);
    });

    it('returns empty issues for secure credentials', function (): void {
        $result = $this->service->validateCredentials([
            'admin_user' => 'john.doe',
            'admin_password' => 'xK9#mP2$vL5@nQ8!',
        ]);

        expect($result['has_issues'])->toBeFalse();
        expect($result['issues'])->toBeEmpty();
    });

    it('masks password values in results', function (): void {
        $result = $this->service->validateCredentials([
            'password' => 'admin',
        ]);

        // Value should be masked, not plain text
        expect($result['issues'][0]['value'])->not->toBe('admin');
        expect($result['issues'][0]['value'])->toContain('*');
    });

    it('detects multiple issues at once', function (): void {
        $result = $this->service->validateCredentials([
            'admin_user' => 'admin',
            'admin_password' => 'admin',
            'api_key' => 'secret',
        ]);

        expect($result['has_issues'])->toBeTrue();
        expect($result['issues'])->toHaveCount(3);
    });
});

// ─── formatWarning() ─────────────────────────────────────────────────────
// Formats issues for display

describe('formatWarning', function (): void {

    it('returns title and lines array', function (): void {
        $issues = [
            ['field' => 'password', 'value' => 'ad**', 'severity' => 'warning', 'message' => 'Test'],
        ];

        $warning = $this->service->formatWarning($issues);

        expect($warning)->toHaveKeys(['title', 'lines']);
        expect($warning['title'])->toBe('Security Warning');
        expect($warning['lines'])->toBeArray();
    });

    it('includes development-only warning', function (): void {
        $issues = [
            ['field' => 'password', 'value' => 'ad**', 'severity' => 'warning', 'message' => 'Test'],
        ];

        $warning = $this->service->formatWarning($issues);
        $content = implode(' ', $warning['lines']);

        expect($content)->toContain('development');
    });

    it('lists each issue in the output', function (): void {
        $issues = [
            ['field' => 'admin_password', 'value' => 'ad**', 'severity' => 'warning', 'message' => 'Insecure password'],
            ['field' => 'admin_user', 'value' => 'admin', 'severity' => 'warning', 'message' => 'Insecure username'],
        ];

        $warning = $this->service->formatWarning($issues);
        $content = implode("\n", $warning['lines']);

        expect($content)->toContain('admin_password');
        expect($content)->toContain('admin_user');
    });
});

// ─── isProductionEnvironment() ───────────────────────────────────────────
// Checks if running in production mode

describe('isProductionEnvironment', function (): void {

    it('returns false when APP_ENV is not production', function (): void {
        // Default should not be production
        expect($this->service->isProductionEnvironment())->toBeFalse();
    });

    it('returns true when APP_ENV is production', function (): void {
        $original = getenv('APP_ENV');
        putenv('APP_ENV=production');

        $service = new CredentialValidationService;
        expect($service->isProductionEnvironment())->toBeTrue();

        // Restore
        putenv('APP_ENV' . ($original === false ? '' : '=' . $original));
    });
});
