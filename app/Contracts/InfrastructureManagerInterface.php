<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for managing global tuti-cli infrastructure.
 *
 * This handles the Traefik reverse proxy and shared Docker network
 * that connects all tuti-cli projects together.
 */
interface InfrastructureManagerInterface
{
    /**
     * Check if the global infrastructure is installed.
     */
    public function isInstalled(): bool;

    /**
     * Check if the infrastructure containers are running.
     */
    public function isRunning(): bool;

    /**
     * Install the global infrastructure (Traefik, networks, etc.).
     *
     * @throws \RuntimeException If installation fails
     */
    public function install(): void;

    /**
     * Start the infrastructure containers.
     *
     * @throws \RuntimeException If start fails
     */
    public function start(): void;

    /**
     * Stop the infrastructure containers.
     *
     * @throws \RuntimeException If stop fails
     */
    public function stop(): void;

    /**
     * Ensure the infrastructure is ready (install if needed, start if stopped).
     *
     * @return bool True if infrastructure is ready
     */
    public function ensureReady(): bool;

    /**
     * Ensure the shared Docker network exists.
     *
     * @param  string  $networkName  Name of the network (default: traefik_proxy)
     * @return bool True if network exists or was created
     */
    public function ensureNetworkExists(string $networkName = 'traefik_proxy'): bool;

    /**
     * Get the path to the infrastructure directory.
     */
    public function getInfrastructurePath(): string;

    /**
     * Get the status of all infrastructure components.
     *
     * @return array<string, array{installed: bool, running: bool, health: string}>
     */
    public function getStatus(): array;
}
