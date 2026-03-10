<?php

declare(strict_types=1);

/**
 * WordPressType Enum Unit Tests.
 *
 * Tests the WordPressType enum which defines WordPress installation types.
 *
 * @see WordPressTypeEnum
 */

use App\Enums\WordPressTypeEnum;

// ─── Enum Cases ───────────────────────────────────────────────────────────

describe('WordPressType Enum Cases', function (): void {

    it('has SINGLE case with correct value', function (): void {
        expect(WordPressTypeEnum::SINGLE->value)->toBe('single');
    });

    it('has BEDROCK case with correct value', function (): void {
        expect(WordPressTypeEnum::BEDROCK->value)->toBe('bedrock');
    });

    it('has exactly two cases', function (): void {
        expect(WordPressTypeEnum::cases())->toHaveCount(2);
    });

    it('is backed by string', function (): void {
        $reflection = new ReflectionEnum(WordPressTypeEnum::class);
        expect($reflection->isBacked())->toBeTrue();
        expect($reflection->getBackingType()->getName())->toBe('string');
    });
});

// ─── Enum Methods ──────────────────────────────────────────────────────────

describe('WordPressType Enum Methods', function (): void {

    it('returns correct description for SINGLE', function (): void {
        expect(WordPressTypeEnum::SINGLE->description())
            ->toContain('Traditional WordPress');
    });

    it('returns correct description for BEDROCK', function (): void {
        expect(WordPressTypeEnum::BEDROCK->description())
            ->toContain('Composer')
            ->toContain('enhanced security');
    });

    it('returns correct label for SINGLE', function (): void {
        expect(WordPressTypeEnum::SINGLE->label())->toBe('Standard WordPress');
    });

    it('returns correct label for BEDROCK', function (): void {
        expect(WordPressTypeEnum::BEDROCK->label())->toBe('Bedrock (Roots)');
    });

    it('isBedrock returns true only for BEDROCK', function (): void {
        expect(WordPressTypeEnum::BEDROCK->isBedrock())->toBeTrue();
        expect(WordPressTypeEnum::SINGLE->isBedrock())->toBeFalse();
    });

    it('isStandard returns true only for SINGLE', function (): void {
        expect(WordPressTypeEnum::SINGLE->isStandard())->toBeTrue();
        expect(WordPressTypeEnum::BEDROCK->isStandard())->toBeFalse();
    });
});

// ─── Enum Serialization ────────────────────────────────────────────────────

describe('WordPressType Enum Serialization', function (): void {

    it('can be created from string value', function (): void {
        expect(WordPressTypeEnum::from('single'))->toBe(WordPressTypeEnum::SINGLE);
        expect(WordPressTypeEnum::from('bedrock'))->toBe(WordPressTypeEnum::BEDROCK);
    });

    it('can be created from string value with tryFrom returning null for invalid', function (): void {
        expect(WordPressTypeEnum::tryFrom('invalid'))->toBeNull();
        expect(WordPressTypeEnum::tryFrom('SINGLE'))->toBeNull(); // Case-sensitive
    });

    it('serializes to string value', function (): void {
        expect(WordPressTypeEnum::SINGLE->value)->toBe('single');
        expect(WordPressTypeEnum::BEDROCK->value)->toBe('bedrock');
    });

    it('can be JSON encoded and decoded', function (): void {
        $encoded = json_encode(WordPressTypeEnum::BEDROCK);
        expect($encoded)->toBe('"bedrock"');

        // Decode back to enum via value
        $decoded = WordPressTypeEnum::from(json_decode($encoded, true));
        expect($decoded)->toBe(WordPressTypeEnum::BEDROCK);
    });
});

// ─── Type Hints ────────────────────────────────────────────────────────────

describe('WordPressType Enum Type Hints', function (): void {

    it('can be used as return type', function (): void {
        $getSingleType = fn (): WordPressTypeEnum => WordPressTypeEnum::SINGLE;
        $getBedrockType = fn (): WordPressTypeEnum => WordPressTypeEnum::BEDROCK;

        expect($getSingleType())->toBeInstanceOf(WordPressTypeEnum::class);
        expect($getBedrockType())->toBeInstanceOf(WordPressTypeEnum::class);
    });

    it('can be used as parameter type', function (): void {
        $isSingle = fn (WordPressTypeEnum $type): bool => $type === WordPressTypeEnum::SINGLE;

        expect($isSingle(WordPressTypeEnum::SINGLE))->toBeTrue();
        expect($isSingle(WordPressTypeEnum::BEDROCK))->toBeFalse();
    });

    it('works in match expressions', function (): void {
        $getDescription = fn (WordPressTypeEnum $type): string => match ($type) {
            WordPressTypeEnum::SINGLE => 'single-site',
            WordPressTypeEnum::BEDROCK => 'modern',
        };

        expect($getDescription(WordPressTypeEnum::SINGLE))->toBe('single-site');
        expect($getDescription(WordPressTypeEnum::BEDROCK))->toBe('modern');
    });
});
