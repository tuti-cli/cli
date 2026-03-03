<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Credential Validation Service.
 *
 * Detects common development credentials that should not be used in production.
 * Provides warnings when insecure credential patterns are found.
 */
final readonly class CredentialValidationService
{
    /**
     * Common insecure password patterns used in development.
     */
    private const array INSECURE_PASSWORDS = [
        'admin',
        'password',
        'secret',
        'changeme',
        '123456',
        'test',
        'demo',
        'dev',
        'development',
    ];

    /**
     * Common insecure username patterns used in development.
     */
    private const array INSECURE_USERNAMES = [
        'admin',
        'root',
        'test',
        'demo',
        'dev',
        'user',
    ];

    /**
     * Check if a password is a known insecure development credential.
     */
    public function isInsecurePassword(string $password): bool
    {
        $normalizedPassword = mb_strtolower(mb_trim($password));

        return in_array($normalizedPassword, self::INSECURE_PASSWORDS, true);
    }

    /**
     * Check if a username is a known insecure development credential.
     */
    public function isInsecureUsername(string $username): bool
    {
        $normalizedUsername = mb_strtolower(mb_trim($username));

        return in_array($normalizedUsername, self::INSECURE_USERNAMES, true);
    }

    /**
     * Check if username/password combination is a known insecure pattern.
     */
    public function isInsecureCredentialPair(string $username, string $password): bool
    {
        // Same username and password is always insecure
        if (mb_strtolower($username) === mb_strtolower($password)) {
            return true;
        }

        return $this->isInsecurePassword($password) && $this->isInsecureUsername($username);
    }

    /**
     * Validate credentials and return detailed findings.
     *
     * @param  array<string, string>  $credentials  Key-value pairs like ['username' => 'admin', 'password' => 'admin']
     * @return array{has_issues: bool, issues: array<int, array{field: string, value: string, severity: string, message: string}>}
     */
    public function validateCredentials(array $credentials): array
    {
        $issues = [];

        foreach ($credentials as $field => $value) {
            $normalizedField = mb_strtolower($field);

            // Check password fields
            if ((str_contains($normalizedField, 'password') || str_contains($normalizedField, 'pass')) && $this->isInsecurePassword($value)) {
                $issues[] = [
                    'field' => $field,
                    'value' => $this->maskValue($value),
                    'severity' => 'warning',
                    'message' => 'Uses common development password pattern',
                ];
            }

            // Check username fields
            if ((str_contains($normalizedField, 'user') || str_contains($normalizedField, 'login')) && $this->isInsecureUsername($value)) {
                $issues[] = [
                    'field' => $field,
                    'value' => $value,
                    'severity' => 'warning',
                    'message' => 'Uses common development username pattern',
                ];
            }

            // Check secret/key fields
            if ((str_contains($normalizedField, 'secret') || str_contains($normalizedField, 'key') || str_contains($normalizedField, 'token')) && $this->isInsecurePassword($value)) {
                $issues[] = [
                    'field' => $field,
                    'value' => $this->maskValue($value),
                    'severity' => 'warning',
                    'message' => 'Uses insecure secret/key value',
                ];
            }
        }

        return [
            'has_issues' => $issues !== [],
            'issues' => $issues,
        ];
    }

    /**
     * Check environment and return if we're in production mode.
     */
    public function isProductionEnvironment(): bool
    {
        $env = getenv('APP_ENV') ?: 'local';

        return mb_strtolower($env) === 'production';
    }

    /**
     * Get warning message for display.
     *
     * @param  array<int, array{field: string, value: string, severity: string, message: string}>  $issues
     * @return array{title: string, lines: array<string>}
     */
    public function formatWarning(array $issues): array
    {
        $lines = [
            'Development credentials detected in configuration.',
            '',
            'These credentials are for local development only.',
            'Never deploy to production with these values.',
        ];

        foreach ($issues as $issue) {
            $lines[] = "  - {$issue['field']}: {$issue['message']}";
        }

        if ($this->isProductionEnvironment()) {
            $lines[] = '';
            $lines[] = 'WARNING: APP_ENV=production detected!';
            $lines[] = 'Change these credentials immediately.';
        }

        return [
            'title' => 'Security Warning',
            'lines' => $lines,
        ];
    }

    /**
     * Mask a sensitive value for display.
     */
    private function maskValue(string $value, int $visibleChars = 2): string
    {
        $length = mb_strlen($value);

        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);
    }
}
