<?php

declare(strict_types=1);

/**
 * MultisiteMode Enum Unit Tests.
 *
 * Tests the MultisiteMode enum which defines WordPress multisite configuration modes.
 *
 * @see App\Enums\MultisiteModeEnum
 */

use App\Enums\MultisiteModeEnum;

// ─── Enum Cases ───────────────────────────────────────────────────────────

describe('MultisiteMode Enum Cases', function (): void {

    it('has NONE case with correct value', function (): void {
        expect(MultisiteModeEnum::NONE->value)->toBe('none');
    });

    it('has SUBDOMAIN case with correct value', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->value)->toBe('subdomain');
    });

    it('has SUBDIRECTORY case with correct value', function (): void {
        expect(MultisiteModeEnum::SUBDIRECTORY->value)->toBe('subdirectory');
    });

    it('has exactly three cases', function (): void {
        expect(MultisiteModeEnum::cases())->toHaveCount(3);
    });

    it('is backed by string', function (): void {
        $reflection = new ReflectionEnum(MultisiteModeEnum::class);
        expect($reflection->isBacked())->toBeTrue();
        expect($reflection->getBackingType()->getName())->toBe('string');
    });
});

// ─── Enum Methods ──────────────────────────────────────────────────────────

describe('MultisiteMode Enum Methods', function (): void {

    it('isEnabled returns true for SUBDOMAIN', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->isEnabled())->toBeTrue();
    });

    it('isEnabled returns true for SUBDIRECTORY', function (): void {
        expect(MultisiteModeEnum::SUBDIRECTORY->isEnabled())->toBeTrue();
    });

    it('isEnabled returns false for NONE', function (): void {
        expect(MultisiteModeEnum::NONE->isEnabled())->toBeFalse();
    });

    it('isDisabled returns true for NONE', function (): void {
        expect(MultisiteModeEnum::NONE->isDisabled())->toBeTrue();
    });

    it('isDisabled returns false for SUBDOMAIN', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->isDisabled())->toBeFalse();
    });

    it('isSubdomain returns true only for SUBDOMAIN', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->isSubdomain())->toBeTrue();
        expect(MultisiteModeEnum::SUBDIRECTORY->isSubdomain())->toBeFalse();
        expect(MultisiteModeEnum::NONE->isSubdomain())->toBeFalse();
    });

    it('isSubdirectory returns true only for SUBDIRECTORY', function (): void {
        expect(MultisiteModeEnum::SUBDIRECTORY->isSubdirectory())->toBeTrue();
        expect(MultisiteModeEnum::SUBDOMAIN->isSubdirectory())->toBeFalse();
        expect(MultisiteModeEnum::NONE->isSubdirectory())->toBeFalse();
    });

    it('returns correct label for NONE', function (): void {
        expect(MultisiteModeEnum::NONE->label())->toBe('Single Site');
    });

    it('returns correct label for SUBDOMAIN', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->label())->toBe('Multisite (Subdomains)');
    });

    it('returns correct label for SUBDIRECTORY', function (): void {
        expect(MultisiteModeEnum::SUBDIRECTORY->label())->toBe('Multisite (Subdirectories)');
    });
});

// ─── SUBDOMAIN_INSTALL Constant ────────────────────────────────────────────

describe('MultisiteMode SUBDOMAIN_INSTALL Constant', function (): void {

    it('returns null for NONE', function (): void {
        expect(MultisiteModeEnum::NONE->getSubdomainInstallConstant())->toBeNull();
    });

    it('returns true for SUBDOMAIN', function (): void {
        expect(MultisiteModeEnum::SUBDOMAIN->getSubdomainInstallConstant())->toBeTrue();
    });

    it('returns false for SUBDIRECTORY', function (): void {
        expect(MultisiteModeEnum::SUBDIRECTORY->getSubdomainInstallConstant())->toBeFalse();
    });

    it('can be used directly in wp-config.php generation', function (): void {
        $generateConstant = fn (MultisiteModeEnum $mode): string => match ($mode->getSubdomainInstallConstant()) {
            null => '// Multisite not enabled',
            true => "define('SUBDOMAIN_INSTALL', true);",
            false => "define('SUBDOMAIN_INSTALL', false);",
        };

        expect($generateConstant(MultisiteModeEnum::NONE))->toBe('// Multisite not enabled');
        expect($generateConstant(MultisiteModeEnum::SUBDOMAIN))->toBe("define('SUBDOMAIN_INSTALL', true);");
        expect($generateConstant(MultisiteModeEnum::SUBDIRECTORY))->toBe("define('SUBDOMAIN_INSTALL', false);");
    });
});

// ─── Enum Serialization ────────────────────────────────────────────────────

describe('MultisiteMode Enum Serialization', function (): void {

    it('can be created from string value', function (): void {
        expect(MultisiteModeEnum::from('none'))->toBe(MultisiteModeEnum::NONE);
        expect(MultisiteModeEnum::from('subdomain'))->toBe(MultisiteModeEnum::SUBDOMAIN);
        expect(MultisiteModeEnum::from('subdirectory'))->toBe(MultisiteModeEnum::SUBDIRECTORY);
    });

    it('can be created from string value with tryFrom returning null for invalid', function (): void {
        expect(MultisiteModeEnum::tryFrom('invalid'))->toBeNull();
        expect(MultisiteModeEnum::tryFrom('NONE'))->toBeNull(); // Case-sensitive
    });

    it('serializes to string value', function (): void {
        expect(MultisiteModeEnum::NONE->value)->toBe('none');
        expect(MultisiteModeEnum::SUBDOMAIN->value)->toBe('subdomain');
        expect(MultisiteModeEnum::SUBDIRECTORY->value)->toBe('subdirectory');
    });

    it('can be JSON encoded and decoded', function (): void {
        $encoded = json_encode(MultisiteModeEnum::SUBDOMAIN);
        expect($encoded)->toBe('"subdomain"');

        // Decode back to enum via value
        $decoded = MultisiteModeEnum::from(json_decode($encoded, true));
        expect($decoded)->toBe(MultisiteModeEnum::SUBDOMAIN);
    });
});

// ─── Type Hints ────────────────────────────────────────────────────────────

describe('MultisiteMode Enum Type Hints', function (): void {

    it('can be used as return type', function (): void {
        $getNone = fn (): MultisiteModeEnum => MultisiteModeEnum::NONE;
        $getSubdomain = fn (): MultisiteModeEnum => MultisiteModeEnum::SUBDOMAIN;

        expect($getNone())->toBeInstanceOf(MultisiteModeEnum::class);
        expect($getSubdomain())->toBeInstanceOf(MultisiteModeEnum::class);
    });

    it('can be used as parameter type', function (): void {
        $isMultisite = fn (MultisiteModeEnum $mode): bool => $mode->isEnabled();

        expect($isMultisite(MultisiteModeEnum::NONE))->toBeFalse();
        expect($isMultisite(MultisiteModeEnum::SUBDOMAIN))->toBeTrue();
        expect($isMultisite(MultisiteModeEnum::SUBDIRECTORY))->toBeTrue();
    });

    it('works in match expressions', function (): void {
        $getLabel = fn (MultisiteModeEnum $mode): string => match ($mode) {
            MultisiteModeEnum::NONE => 'single',
            MultisiteModeEnum::SUBDOMAIN => 'subdomain',
            MultisiteModeEnum::SUBDIRECTORY => 'subdir',
        };

        expect($getLabel(MultisiteModeEnum::NONE))->toBe('single');
        expect($getLabel(MultisiteModeEnum::SUBDOMAIN))->toBe('subdomain');
        expect($getLabel(MultisiteModeEnum::SUBDIRECTORY))->toBe('subdir');
    });
});
