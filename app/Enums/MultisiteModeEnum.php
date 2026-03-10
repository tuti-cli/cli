<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum for WordPress multisite modes.
 *
 * Defines the different multisite configuration modes available in WordPress.
 * WordPress multisite allows multiple sites to share a single WordPress installation.
 *
 * @example
 *   MultisiteMode::NONE        // Single site (default)
 *   MultisiteMode::SUBDOMAIN   // Multisite with subdomains (site1.example.com)
 *   MultisiteMode::SUBDIRECTORY // Multisite with subdirectories (example.com/site1)
 */
enum MultisiteModeEnum: string
{
    case NONE = 'none';
    case SUBDOMAIN = 'subdomain';
    case SUBDIRECTORY = 'subdirectory';

    /**
     * Check if multisite is disabled (single site mode).
     */
    public function isDisabled(): bool
    {
        return $this === self::NONE;
    }

    /**
     * Check if multisite is enabled.
     */
    public function isEnabled(): bool
    {
        return $this !== self::NONE;
    }

    /**
     * Check if this is subdomain mode.
     */
    public function isSubdomain(): bool
    {
        return $this === self::SUBDOMAIN;
    }

    /**
     * Check if this is subdirectory mode.
     */
    public function isSubdirectory(): bool
    {
        return $this === self::SUBDIRECTORY;
    }

    /**
     * Get human-readable label for the multisite mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Single Site',
            self::SUBDOMAIN => 'Multisite (Subdomains)',
            self::SUBDIRECTORY => 'Multisite (Subdirectories)',
        };
    }

    /**
     * Get the SUBDOMAIN_INSTALL constant value for wp-config.php.
     *
     * @return bool|null true for subdomains, false for subdirectories, null for single site
     */
    public function getSubdomainInstallConstant(): ?bool
    {
        return match ($this) {
            self::NONE => null,
            self::SUBDOMAIN => true,
            self::SUBDIRECTORY => false,
        };
    }
}
