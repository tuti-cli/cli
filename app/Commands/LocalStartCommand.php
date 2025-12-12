<?php

namespace App\Commands;

use App\Services\DockerService;
use App\Support\SystemEnvironment;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class LocalStartCommand extends Command
{
    protected $signature = 'local:test';

    protected $description = 'Start the local development environment';

    public function handle(): int
    {
        $this->info('Testing dummy command execution...');
        $this->newLine();

        dd([
            tuti_path(),
            discover_stacks(),
            tuti_exists(),
            stub_path(),
            mask_sensitive('Password123!', 'Password123!'),
            stack_path(),
            global_tuti_path(),
        ]);

        return self::SUCCESS;
    }
}
