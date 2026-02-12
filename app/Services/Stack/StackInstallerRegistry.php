<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Contracts\StackInstallerInterface;
use InvalidArgumentException;

/**
 * Registry for managing stack installers.
 *
 * This service maintains a collection of all available stack installers
 * and provides methods to discover, register, and retrieve them.
 */
final class StackInstallerRegistry
{
    /**
     * @var array<string, StackInstallerInterface>
     */
    private array $installers = [];

    /**
     * Register a stack installer.
     */
    public function register(StackInstallerInterface $installer): void
    {
        $this->installers[$installer->getIdentifier()] = $installer;
    }

    /**
     * Get an installer by its identifier.
     *
     * @throws InvalidArgumentException If installer is not found
     */
    public function get(string $identifier): StackInstallerInterface
    {
        // Try exact match first
        if (isset($this->installers[$identifier])) {
            return $this->installers[$identifier];
        }

        // Try to find by supports() method
        foreach ($this->installers as $installer) {
            if ($installer->supports($identifier)) {
                return $installer;
            }
        }

        throw new InvalidArgumentException("Stack installer not found: {$identifier}");
    }

    /**
     * Check if an installer exists for the given identifier.
     */
    public function has(string $identifier): bool
    {
        if (isset($this->installers[$identifier])) {
            return true;
        }
        return array_any($this->installers, fn($installer) => $installer->supports($identifier));
    }

    /**
     * Get all registered installers.
     *
     * @return array<string, StackInstallerInterface>
     */
    public function all(): array
    {
        return $this->installers;
    }

    /**
     * Get a list of available stacks for selection.
     *
     * @return array<string, array{name: string, description: string, framework: string}>
     */
    public function getAvailableStacks(): array
    {
        $stacks = [];

        foreach ($this->installers as $identifier => $installer) {
            $stacks[$identifier] = [
                'name' => $installer->getName(),
                'description' => $installer->getDescription(),
                'framework' => $installer->getFramework(),
            ];
        }

        return $stacks;
    }

    /**
     * Detect which installer can handle the current project.
     *
     * @return StackInstallerInterface|null The matching installer or null
     */
    public function detectForProject(string $path): ?StackInstallerInterface
    {
        foreach ($this->installers as $installer) {
            if ($installer->detectExistingProject($path)) {
                return $installer;
            }
        }

        return null;
    }
}
