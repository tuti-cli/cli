<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use App\Services\Debug\DebugLogService;
use App\Services\Project\ProjectDirectoryService;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class DoctorCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'doctor
                          {--fix : Attempt to fix issues automatically}';

    protected $description = 'Check system requirements and diagnose issues';

    public function handle(
        InfrastructureManagerInterface $infrastructureManager,
        ProjectDirectoryService $dirService,
        DebugLogService $debugService
    ): int {
        $this->brandedHeader('System Health Check');

        $issues = [];
        $warnings = [];

        // 1. Check Docker
        $this->section('Docker');
        $dockerResult = $this->checkDocker();
        if ($dockerResult['status'] === 'error') {
            $issues[] = $dockerResult;
        } elseif ($dockerResult['status'] === 'warning') {
            $warnings[] = $dockerResult;
        }

        // 2. Check Docker Compose
        $this->section('Docker Compose');
        $composeResult = $this->checkDockerCompose();
        if ($composeResult['status'] === 'error') {
            $issues[] = $composeResult;
        }

        // 3. Check Global Tuti Directory
        $this->section('Global Configuration');
        $globalResult = $this->checkGlobalConfig();
        if ($globalResult['status'] === 'error') {
            $issues[] = $globalResult;
        }

        // 4. Check Infrastructure (Traefik)
        $this->section('Infrastructure');
        $infraResult = $this->checkInfrastructure($infrastructureManager);
        if ($infraResult['status'] === 'error') {
            $issues[] = $infraResult;
        } elseif ($infraResult['status'] === 'warning') {
            $warnings[] = $infraResult;
        }

        // 5. Check Current Project (if in project directory)
        if ($dirService->exists()) {
            $this->section('Current Project');
            $projectResults = $this->checkCurrentProject($dirService);
            foreach ($projectResults as $result) {
                if ($result['status'] === 'error') {
                    $issues[] = $result;
                } elseif ($result['status'] === 'warning') {
                    $warnings[] = $result;
                }
            }
        }

        // 6. Check Debug Mode
        $this->section('Debug');
        $this->checkDebugMode($debugService);

        // Summary
        $this->newLine();
        $this->section('Summary');

        if (empty($issues) && empty($warnings)) {
            $this->success('All checks passed! Your system is ready to use tuti-cli.');
            return self::SUCCESS;
        }

        if (! empty($warnings)) {
            $this->warning(count($warnings) . ' warning(s) found:');
            foreach ($warnings as $warning) {
                $this->line("  ⚠️  {$warning['message']}");
                if (isset($warning['hint'])) {
                    $this->line("     <fg=gray>{$warning['hint']}</>");
                }
            }
            $this->newLine();
        }

        if (! empty($issues)) {
            $this->failure(count($issues) . ' issue(s) found:');
            foreach ($issues as $issue) {
                $this->line("  ❌ {$issue['message']}");
                if (isset($issue['hint'])) {
                    $this->line("     <fg=gray>{$issue['hint']}</>");
                }
            }
            $this->newLine();

            $this->hint('Fix the issues above and run "tuti doctor" again');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkDocker(): array
    {
        $process = Process::run(['docker', '--version']);

        if (! $process->successful()) {
            $this->line('  ❌ Docker not found');
            return [
                'status' => 'error',
                'message' => 'Docker is not installed',
                'hint' => 'Install Docker Desktop from https://www.docker.com/products/docker-desktop',
            ];
        }

        $version = trim($process->output());
        $this->line("  ✅ {$version}");

        // Check if Docker daemon is running
        $infoProcess = Process::run(['docker', 'info']);
        if (! $infoProcess->successful()) {
            $this->line('  ❌ Docker daemon not running');
            return [
                'status' => 'error',
                'message' => 'Docker daemon is not running',
                'hint' => 'Start Docker Desktop or run "sudo systemctl start docker"',
            ];
        }

        $this->line('  ✅ Docker daemon is running');

        return ['status' => 'ok'];
    }

    private function checkDockerCompose(): array
    {
        $process = Process::run(['docker', 'compose', 'version']);

        if (! $process->successful()) {
            $this->line('  ❌ Docker Compose not found');
            return [
                'status' => 'error',
                'message' => 'Docker Compose is not available',
                'hint' => 'Docker Compose V2 is included with Docker Desktop',
            ];
        }

        $version = trim($process->output());
        $this->line("  ✅ {$version}");

        return ['status' => 'ok'];
    }

    private function checkGlobalConfig(): array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $globalPath = rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti';

        if (! is_dir($globalPath)) {
            $this->line('  ❌ Global directory not found: ' . $globalPath);
            return [
                'status' => 'error',
                'message' => 'Global tuti directory not found',
                'hint' => 'Run "tuti install" to set up tuti-cli',
            ];
        }

        $this->line("  ✅ Global directory: {$globalPath}");

        // Check config.json
        $configFile = $globalPath . '/config.json';
        if (! file_exists($configFile)) {
            $this->line('  ⚠️  config.json not found');
        } else {
            $this->line('  ✅ config.json exists');
        }

        return ['status' => 'ok'];
    }

    private function checkInfrastructure(InfrastructureManagerInterface $infraManager): array
    {
        if (! $infraManager->isInstalled()) {
            $this->line('  ❌ Traefik not installed');
            return [
                'status' => 'error',
                'message' => 'Traefik infrastructure not installed',
                'hint' => 'Run "tuti install" to set up infrastructure',
            ];
        }

        $this->line('  ✅ Traefik installed');

        if (! $infraManager->isRunning()) {
            $this->line('  ⚠️  Traefik not running');
            return [
                'status' => 'warning',
                'message' => 'Traefik is installed but not running',
                'hint' => 'Run "tuti infra:start" to start Traefik',
            ];
        }

        $this->line('  ✅ Traefik is running');

        return ['status' => 'ok'];
    }

    private function checkCurrentProject(ProjectDirectoryService $dirService): array
    {
        $results = [];
        $tutiPath = $dirService->getTutiPath();

        $this->line("  📁 .tuti directory: {$tutiPath}");

        // Check config.json
        $configFile = $tutiPath . '/config.json';
        if (! file_exists($configFile)) {
            $this->line('  ❌ config.json not found');
            $results[] = [
                'status' => 'error',
                'message' => 'Project config.json not found',
                'hint' => 'The project may not be properly initialized',
            ];
        } else {
            $this->line('  ✅ config.json exists');
        }

        // Check docker-compose.yml
        $composeFile = $tutiPath . '/docker-compose.yml';
        if (! file_exists($composeFile)) {
            $this->line('  ❌ docker-compose.yml not found');
            $results[] = [
                'status' => 'error',
                'message' => 'docker-compose.yml not found in .tuti/',
                'hint' => 'Reinitialize the project with "tuti stack:laravel --force"',
            ];
        } else {
            $this->line('  ✅ docker-compose.yml exists');

            // Check compose file syntax
            $validateProcess = Process::path($tutiPath)->run(['docker', 'compose', 'config', '--quiet']);
            if (! $validateProcess->successful()) {
                $this->line('  ❌ docker-compose.yml has errors');
                $this->line("     <fg=yellow>" . trim($validateProcess->errorOutput()) . "</>");
                $results[] = [
                    'status' => 'error',
                    'message' => 'docker-compose.yml has syntax errors',
                    'hint' => 'Check the compose file for errors',
                ];
            } else {
                $this->line('  ✅ docker-compose.yml syntax valid');
            }
        }

        // Check Dockerfile
        $dockerfile = $tutiPath . '/docker/Dockerfile';
        if (! file_exists($dockerfile)) {
            $this->line('  ⚠️  Dockerfile not found');
            $results[] = [
                'status' => 'warning',
                'message' => 'Dockerfile not found in .tuti/docker/',
            ];
        } else {
            $this->line('  ✅ Dockerfile exists');
        }

        // Check .env
        $envFile = $tutiPath . '/.env';
        if (! file_exists($envFile)) {
            $this->line('  ⚠️  .env not found in .tuti/');
        } else {
            $this->line('  ✅ .env exists');
        }

        return $results;
    }

    private function checkDebugMode(DebugLogService $debugService): void
    {
        if ($debugService->isEnabled()) {
            $this->line('  ✅ Debug mode: ENABLED');
            $this->line("     Log path: {$debugService->getGlobalLogPath()}");
        } else {
            $this->line('  ℹ️  Debug mode: disabled');
            $this->line('     <fg=gray>Run "tuti debug enable" for detailed logging</>');
        }

        $errors = $debugService->getErrors();
        $errorCount = count($errors);
        if ($errorCount > 0) {
            $this->line("  ⚠️  {$errorCount} error(s) in session");
            $this->line('     <fg=gray>Run "tuti debug errors" to view</>');
        }
    }
}
