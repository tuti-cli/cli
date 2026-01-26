<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for stack installers.
 *
 * Each stack (Laravel, WordPress, etc.) should have its own installer
 * that implements this interface. The installer handles both fresh
 * installations and applying stack configuration to existing projects.
 */
interface StackInstallerInterface
{
    /**
     * Get the unique identifier for this stack.
     * Used for registration and lookup.
     */
    public function getIdentifier(): string;

    /**
     * Get the display name for this stack.
     */
    public function getName(): string;

    /**
     * Get the description of this stack.
     */
    public function getDescription(): string;

    /**
     * Get the framework type (laravel, wordpress, etc.).
     */
    public function getFramework(): string;

    /**
     * Check if this installer can handle the given stack.
     */
    public function supports(string $stackIdentifier): bool;

    /**
     * Check if the current directory contains an existing project
     * that this installer can work with.
     */
    public function detectExistingProject(string $path): bool;

    /**
     * Install a fresh project with the stack configuration.
     *
     * @param  array<string, mixed>  $options  Installation options
     * @return bool True if installation was successful
     */
    public function installFresh(string $projectPath, string $projectName, array $options = []): bool;

    /**
     * Apply stack configuration to an existing project.
     *
     * @param  array<string, mixed>  $options  Configuration options
     * @return bool True if configuration was applied successfully
     */
    public function applyToExisting(string $projectPath, array $options = []): bool;

    /**
     * Get the path to the stack template directory.
     */
    public function getStackPath(): string;

    /**
     * Get available installation modes.
     *
     * @return array<string, string> Mode key => description
     */
    public function getAvailableModes(): array;
}
