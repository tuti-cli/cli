<?php

namespace App\Commands;

use App\Services\DockerService;
use App\Support\SystemEnvironment;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class LocalStartCommand extends Command
{
    protected $signature = 'local:start';

    protected $description = 'Start the local development environment';

    public function handle(DockerService $docker): void
    {
        $this->info('Starting local development environment');

        if ($docker->isRunning()) {
            $this->info('Docker is running. Starting services...');

            if ($docker->start()) {
                $this->info('Local development environment started successfully.');
                $docker->stop();
            } else {
                $this->error('Failed to start local development environment.');
            }
        } else {
            $this->error('Docker is not running. Please start Docker and try again.');
        }
    }
}
