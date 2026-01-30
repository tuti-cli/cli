<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

use App\Contracts\InfrastructureManagerInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Global Infrastructure Manager.
 *
 * Manages the global Traefik reverse proxy and shared Docker network
 * that connects all tuti-cli projects together.
 *
 * Infrastructure is installed at ~/.tuti/infrastructure/traefik/
 */
final class GlobalInfrastructureManager implements InfrastructureManagerInterface
{
    private const INFRASTRUCTURE_DIR = 'infrastructure';
    private const TRAEFIK_DIR = 'traefik';
    private const NETWORK_NAME = 'traefik_proxy';
    private const COMPOSE_PROJECT_NAME = 'tuti-traefik';

    public function __construct(
        private readonly string $globalTutiPath,
    ) {}

    public function isInstalled(): bool
    {
        $traefikPath = $this->getTraefikPath();

        return file_exists($traefikPath . '/docker-compose.yml');
    }

    public function isRunning(): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        $process = Process::run(
            "docker compose -p " . self::COMPOSE_PROJECT_NAME . " ps --format json"
        );

        if (! $process->successful()) {
            return false;
        }

        $output = trim($process->output());
        if (empty($output)) {
            return false;
        }

        // Parse NDJSON output
        foreach (explode("\n", $output) as $line) {
            if (empty($line)) {
                continue;
            }

            $container = json_decode($line, true);
            if ($container && isset($container['State']) && $container['State'] === 'running') {
                return true;
            }
        }

        return false;
    }

    public function install(): void
    {
        if ($this->isInstalled()) {
            return;
        }

        $traefikPath = $this->getTraefikPath();
        $stubsPath = $this->getStubsPath();

        if (! is_dir($stubsPath)) {
            throw new RuntimeException(
                "Infrastructure stubs not found at: {$stubsPath}"
            );
        }

        // Create infrastructure directory
        if (! is_dir($traefikPath)) {
            if (! mkdir($traefikPath, 0755, true) && ! is_dir($traefikPath)) {
                throw new RuntimeException("Failed to create directory: {$traefikPath}");
            }
        }

        // Copy all files from stubs
        $this->copyDirectory($stubsPath, $traefikPath);

        // Create required directories
        $this->createRequiredDirectories($traefikPath);

        // Create default .env file
        $this->createEnvFile($traefikPath);

        // Generate self-signed certificates if mkcert is not available
        $this->setupCertificates($traefikPath);
    }

    public function start(): void
    {
        if (! $this->isInstalled()) {
            throw new RuntimeException(
                'Infrastructure is not installed. Run "tuti install" first.'
            );
        }

        // Ensure network exists
        $this->ensureNetworkExists();

        $traefikPath = $this->getTraefikPath();

        $process = Process::path($traefikPath)->run(
            "docker compose -p " . self::COMPOSE_PROJECT_NAME . " up -d"
        );

        if (! $process->successful()) {
            throw new RuntimeException(
                "Failed to start infrastructure: " . $process->errorOutput()
            );
        }
    }

    public function stop(): void
    {
        if (! $this->isInstalled()) {
            return;
        }

        $traefikPath = $this->getTraefikPath();

        Process::path($traefikPath)->run(
            "docker compose -p " . self::COMPOSE_PROJECT_NAME . " down"
        );
    }

    public function ensureReady(): bool
    {
        // Check Docker first
        if (! $this->isDockerAvailable()) {
            throw new RuntimeException(
                'Docker is not available. Please ensure Docker is installed and running.'
            );
        }

        // Install if not installed
        if (! $this->isInstalled()) {
            $this->install();
        }

        // Start if not running
        if (! $this->isRunning()) {
            $this->start();
        }

        // Ensure network exists
        $this->ensureNetworkExists();

        return $this->isRunning();
    }

    public function ensureNetworkExists(string $networkName = self::NETWORK_NAME): bool
    {
        // Check if network exists
        $process = Process::run("docker network inspect {$networkName}");

        if ($process->successful()) {
            return true;
        }

        // Create network
        $process = Process::run("docker network create {$networkName}");

        if (! $process->successful()) {
            throw new RuntimeException(
                "Failed to create Docker network '{$networkName}': " . $process->errorOutput()
            );
        }

        return true;
    }

    public function getInfrastructurePath(): string
    {
        return $this->globalTutiPath . DIRECTORY_SEPARATOR . self::INFRASTRUCTURE_DIR;
    }

    public function getStatus(): array
    {
        $status = [
            'traefik' => [
                'installed' => $this->isInstalled(),
                'running' => false,
                'health' => 'unknown',
            ],
            'network' => [
                'installed' => $this->networkExists(),
                'running' => $this->networkExists(),
                'health' => $this->networkExists() ? 'healthy' : 'missing',
            ],
        ];

        if ($status['traefik']['installed']) {
            $status['traefik']['running'] = $this->isRunning();
            $status['traefik']['health'] = $status['traefik']['running'] ? 'healthy' : 'stopped';
        } else {
            $status['traefik']['health'] = 'not_installed';
        }

        return $status;
    }

    /**
     * Get path to Traefik directory.
     */
    private function getTraefikPath(): string
    {
        return $this->getInfrastructurePath() . DIRECTORY_SEPARATOR . self::TRAEFIK_DIR;
    }

    /**
     * Get path to infrastructure stubs.
     */
    private function getStubsPath(): string
    {
        return base_path('stubs/infrastructure/traefik');
    }

    /**
     * Check if Docker is available.
     */
    private function isDockerAvailable(): bool
    {
        $process = Process::run('docker info');

        return $process->successful();
    }

    /**
     * Check if the shared network exists.
     */
    private function networkExists(): bool
    {
        $process = Process::run("docker network inspect " . self::NETWORK_NAME);

        return $process->successful();
    }

    /**
     * Copy directory recursively.
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $directoryIterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $subPathname = $iterator->getSubPathname();
            $targetPath = $destination . DIRECTORY_SEPARATOR . $subPathname;

            if ($item->isDir()) {
                if (! is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Create required directories for Traefik.
     */
    private function createRequiredDirectories(string $traefikPath): void
    {
        $dirs = [
            'certs',
            'secrets',
            'dynamic',
        ];

        foreach ($dirs as $dir) {
            $path = $traefikPath . DIRECTORY_SEPARATOR . $dir;
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Create default .env file for Traefik.
     */
    private function createEnvFile(string $traefikPath): void
    {
        $envPath = $traefikPath . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($envPath)) {
            return;
        }

        $examplePath = $traefikPath . DIRECTORY_SEPARATOR . '.env.example';

        if (file_exists($examplePath)) {
            copy($examplePath, $envPath);
        } else {
            $content = <<<ENV
# Traefik Configuration
TZ=UTC
ACME_EMAIL=admin@local.test

# Dashboard credentials (generated)
TRAEFIK_DASHBOARD_USER=admin
TRAEFIK_DASHBOARD_PASSWORD=
ENV;
            file_put_contents($envPath, $content);
        }
    }

    /**
     * Setup SSL certificates for local development.
     */
    private function setupCertificates(string $traefikPath): void
    {
        $certsPath = $traefikPath . DIRECTORY_SEPARATOR . 'certs';
        $certFile = $certsPath . DIRECTORY_SEPARATOR . 'local-cert.pem';
        $keyFile = $certsPath . DIRECTORY_SEPARATOR . 'local-key.pem';

        if (file_exists($certFile) && file_exists($keyFile)) {
            return;
        }

        // Try mkcert first
        $mkcertProcess = Process::run('mkcert --version');

        if ($mkcertProcess->successful()) {
            Process::path($certsPath)->run(
                'mkcert -cert-file local-cert.pem -key-file local-key.pem "*.local.test" localhost 127.0.0.1 ::1'
            );
        } else {
            // Generate self-signed certificate with OpenSSL
            $this->generateSelfSignedCertificate($certsPath);
        }

        // Create secrets/users file for Traefik dashboard auth
        $this->createAuthFile($traefikPath);
    }

    /**
     * Generate self-signed certificate using OpenSSL.
     */
    private function generateSelfSignedCertificate(string $certsPath): void
    {
        $certFile = $certsPath . DIRECTORY_SEPARATOR . 'local-cert.pem';
        $keyFile = $certsPath . DIRECTORY_SEPARATOR . 'local-key.pem';

        // Generate private key
        Process::run(
            "openssl genrsa -out \"{$keyFile}\" 2048"
        );

        // Generate certificate
        $subject = '/CN=*.local.test/O=Tuti CLI/C=US';
        Process::run(
            "openssl req -new -x509 -key \"{$keyFile}\" -out \"{$certFile}\" -days 365 -subj \"{$subject}\""
        );
    }

    /**
     * Create authentication file for Traefik dashboard.
     */
    private function createAuthFile(string $traefikPath): void
    {
        $secretsPath = $traefikPath . DIRECTORY_SEPARATOR . 'secrets';
        $usersFile = $secretsPath . DIRECTORY_SEPARATOR . 'users';

        if (file_exists($usersFile)) {
            return;
        }

        // Generate a random password and hash it
        $password = bin2hex(random_bytes(16));

        // Try htpasswd first
        $process = Process::run("htpasswd -nb admin {$password}");

        if ($process->successful()) {
            file_put_contents($usersFile, trim($process->output()));
        } else {
            // Fallback: create a simple auth line (not hashed, for development only)
            // In production, user should run htpasswd manually
            file_put_contents($usersFile, "admin:\$apr1\$placeholder\$placeholder");
        }

        // Store password in .env
        $envPath = $traefikPath . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            $envContent = preg_replace(
                '/^TRAEFIK_DASHBOARD_PASSWORD=.*$/m',
                "TRAEFIK_DASHBOARD_PASSWORD={$password}",
                $envContent
            );
            file_put_contents($envPath, $envContent);
        }
    }

    /**
     * Get the Traefik dashboard URL.
     */
    public function getDashboardUrl(): string
    {
        return 'https://traefik.local.test';
    }

    /**
     * Restart the infrastructure.
     */
    public function restart(): void
    {
        $this->stop();
        $this->start();
    }
}
