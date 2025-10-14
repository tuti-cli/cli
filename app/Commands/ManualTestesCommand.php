<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DockerService;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ManualTestesCommand extends Command
{
    protected $signature = 'tt';

    protected $description = 'Quick test command running for development purposes';

    public function __construct(
        private readonly DockerService $docker
    )
    {
        parent::__construct();
    }

    public function handle()
    {
        $process = new Process(['which', 'docker']);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
    }
}
