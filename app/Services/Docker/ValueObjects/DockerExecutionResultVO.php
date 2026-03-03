<?php

declare(strict_types=1);

namespace App\Services\Docker\ValueObjects;

/**
 * Value object for Docker execution results.
 */
final readonly class DockerExecutionResultVO
{
    public function __construct(
        public bool $successful,
        public string $output,
        public string $errorOutput,
        public int $exitCode,
    ) {}

    public static function success(string $output): self
    {
        return new self(
            successful: true,
            output: $output,
            errorOutput: '',
            exitCode: 0,
        );
    }

    public static function failure(string $errorOutput, int $exitCode = 1): self
    {
        return new self(
            successful: false,
            output: '',
            errorOutput: $errorOutput,
            exitCode: $exitCode,
        );
    }
}
