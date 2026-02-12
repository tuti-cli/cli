<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

/**
 * Trait for using stack fixtures in tests.
 *
 * This trait provides methods to use test fixtures instead of production
 * stack files. Fixtures are located in tests/Fixtures/stacks/.
 *
 * Usage:
 *   beforeEach(function (): void {
 *       $this->useStackFixture('test-stack');
 *   });
 */
trait UsesStackFixtures
{
    /**
     * Path to the fixtures directory.
     */
    protected string $fixturesStacksPath;

    /**
     * Original stack_path function reference (if bound).
     */
    protected mixed $originalStackPath;

    /**
     * Set up the test to use stack fixtures.
     *
     * This method binds a custom implementation of stack_path() helper
     * to return paths within the fixtures directory.
     */
    protected function useStackFixture(string $stackName = 'test-stack'): void
    {
        $this->fixturesStacksPath = dirname(__DIR__, 3) . '/Fixtures/stacks';

        // Store the fixture stack path for tests to use
        $this->testStackPath = $this->fixturesStacksPath . '/' . $stackName;
    }

    /**
     * Get the path to a fixture stack.
     */
    protected function getFixtureStackPath(string $stackName = 'test-stack'): string
    {
        return dirname(__DIR__, 3) . '/Fixtures/stacks/' . $stackName;
    }

    /**
     * Get the path to a fixture file within a stack.
     */
    protected function getFixturePath(string $stackName, string $relativePath): string
    {
        return $this->getFixtureStackPath($stackName) . '/' . mb_ltrim($relativePath, '/');
    }

    /**
     * Check if a fixture stack exists.
     */
    protected function fixtureStackExists(string $stackName): bool
    {
        return is_dir($this->getFixtureStackPath($stackName))
            && file_exists($this->getFixtureStackPath($stackName) . '/stack.json');
    }

    /**
     * Get the test stack path for commands that need to discover stacks.
     * Returns the parent directory containing all fixture stacks.
     */
    protected function getFixturesStacksDirectory(): string
    {
        return dirname(__DIR__, 3) . '/Fixtures/stacks';
    }
}
