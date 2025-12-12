<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

final class LocalStartCommand extends Command
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
