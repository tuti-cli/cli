<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidJson', function () {
    $decoded = json_decode((string) $this->value, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray();

    return $this;
});

expect()->extend('toBeDirectory', function () {
    expect(is_dir($this->value))->toBeTrue("Expected {$this->value} to be a directory");

    return $this;
});

expect()->extend('toBeExecutable', function () {
    expect(is_executable($this->value))->toBeTrue("Expected {$this->value} to be executable");

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createTestDirectory(): string
{
    $dir = sys_get_temp_dir() . '/tuti-test-' . bin2hex(random_bytes(8));
    
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return $dir;
}

function cleanupTestDirectory(string $dir): void
{
    if (is_dir($dir)) {
        File::deleteDirectory($dir);
    }
}
