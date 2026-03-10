<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum for WordPress installation types.
 *
 * Defines the different WordPress installation types supported by Tuti CLI.
 * Each type has its own directory structure and configuration requirements.
 *
 * @example
 *   WordPressType::SINGLE   // Standard WordPress installation
 *   WordPressType::BEDROCK  // Bedrock (Roots) modern WordPress stack
 */
enum WordPressTypeEnum: string
{
    case SINGLE = 'single';
    case BEDROCK = 'bedrock';

    /**
     * Check if this is a standard WordPress installation.
     */
    public function isStandard(): bool
    {
        return $this === self::SINGLE;
    }

    /**
     * Check if this is a Bedrock installation.
     */
    public function isBedrock(): bool
    {
        return $this === self::BEDROCK;
    }

    /**
     * Get human-readable label for the installation type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Standard WordPress',
            self::BEDROCK => 'Bedrock (Roots)',
        };
    }

    /**
     * Get description for the installation type.
     */
    public function description(): string
    {
        return match ($this) {
            self::SINGLE => 'Traditional WordPress installation with classic file structure',
            self::BEDROCK => 'Modern WordPress development with Composer and enhanced security',
        };
    }
}
