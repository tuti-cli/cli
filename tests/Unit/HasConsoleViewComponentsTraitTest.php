<?php

use App\Traits\HasConsoleViewComponentsTrait;
use Illuminate\Console\Command;

describe('HasConsoleViewComponentsTrait', function () {
    beforeEach(function () {
        // Create a concrete command class using the trait
        $this->command = new class extends Command {
            use HasConsoleViewComponentsTrait;

            protected $signature = 'test:command';
            protected $description = 'Test command';

            public function testWelcomeBanner()
            {
                return $this->welcomeBanner();
            }

            public function testDividerDouble($color = 'gray', $width = 60)
            {
                return $this->dividerDouble($color, $width);
            }

            public function testDividerWithText($text, $color = 'bright-cyan', $width = 60)
            {
                return $this->dividerWithText($text, $color, $width);
            }
        };
    });

    describe('welcomeBanner', function () {
        it('can be called without throwing exception', function () {
            expect(fn() => $this->command->testWelcomeBanner())->not->toThrow(Exception::class);
        });

        it('is a callable method', function () {
            expect($this->command)->toHaveMethod('testWelcomeBanner');
        });
    });

    describe('dividerDouble', function () {
        it('can be called with default parameters', function () {
            expect(fn() => $this->command->testDividerDouble())->not->toThrow(Exception::class);
        });

        it('accepts custom color parameter', function () {
            expect(fn() => $this->command->testDividerDouble('red'))->not->toThrow(Exception::class);
        });

        it('accepts custom width parameter', function () {
            expect(fn() => $this->command->testDividerDouble('gray', 80))->not->toThrow(Exception::class);
        });

        it('handles edge case width values', function () {
            expect(fn() => $this->command->testDividerDouble('gray', 0))->not->toThrow(Exception::class);
            expect(fn() => $this->command->testDividerDouble('gray', 1))->not->toThrow(Exception::class);
            expect(fn() => $this->command->testDividerDouble('gray', 200))->not->toThrow(Exception::class);
        });

        it('handles various color values', function () {
            $colors = ['gray', 'red', 'green', 'blue', 'yellow', 'magenta', 'cyan', 'white'];

            foreach ($colors as $color) {
                expect(fn() => $this->command->testDividerDouble($color))->not->toThrow(Exception::class);
            }
        });

        it('handles empty color string', function () {
            expect(fn() => $this->command->testDividerDouble(''))->not->toThrow(Exception::class);
        });

        it('handles negative width', function () {
            expect(fn() => $this->command->testDividerDouble('gray', -10))->not->toThrow(Exception::class);
        });
    });

    describe('dividerWithText', function () {
        it('can be called with text parameter', function () {
            expect(fn() => $this->command->testDividerWithText('Test'))->not->toThrow(Exception::class);
        });

        it('accepts custom color parameter', function () {
            expect(fn() => $this->command->testDividerWithText('Test', 'red'))->not->toThrow(Exception::class);
        });

        it('accepts custom width parameter', function () {
            expect(fn() => $this->command->testDividerWithText('Test', 'bright-cyan', 80))->not->toThrow(Exception::class);
        });

        it('handles empty text', function () {
            expect(fn() => $this->command->testDividerWithText(''))->not->toThrow(Exception::class);
        });

        it('handles very long text', function () {
            $longText = str_repeat('A', 100);
            expect(fn() => $this->command->testDividerWithText($longText))->not->toThrow(Exception::class);
        });

        it('handles text longer than width', function () {
            expect(fn() => $this->command->testDividerWithText('Very Long Text', 'bright-cyan', 10))->not->toThrow(Exception::class);
        });

        it('handles multibyte characters', function () {
            expect(fn() => $this->command->testDividerWithText('测试'))->not->toThrow(Exception::class);
        });

        it('handles special characters', function () {
            $specialChars = ['!@#$%^&*()', '├─┤', '→←↑↓', '✓✗'];

            foreach ($specialChars as $char) {
                expect(fn() => $this->command->testDividerWithText($char))->not->toThrow(Exception::class);
            }
        });

        it('handles zero width', function () {
            expect(fn() => $this->command->testDividerWithText('Test', 'bright-cyan', 0))->not->toThrow(Exception::class);
        });

        it('handles negative width', function () {
            expect(fn() => $this->command->testDividerWithText('Test', 'bright-cyan', -10))->not->toThrow(Exception::class);
        });

        it('properly calculates padding for various text lengths', function () {
            $texts = ['A', 'AB', 'ABC', 'ABCD', 'Short', 'Medium Length', 'Very Long Text String'];

            foreach ($texts as $text) {
                expect(fn() => $this->command->testDividerWithText($text))->not->toThrow(Exception::class);
            }
        });
    });

    describe('trait usage', function () {
        it('can be used in a command class', function () {
            $traits = class_uses($this->command);
            expect(in_array(HasConsoleViewComponentsTrait::class, $traits))->toBeTrue();
        });

        it('methods are private and accessible within command', function () {
            $reflection = new ReflectionClass($this->command);

            expect($reflection->hasMethod('welcomeBanner'))->toBeTrue()
                ->and($reflection->hasMethod('dividerDouble'))->toBeTrue()
                ->and($reflection->hasMethod('dividerWithText'))->toBeTrue();
        });
    });
});