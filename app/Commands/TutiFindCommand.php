<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\suggest;

final class TutiFindCommand extends Command
{
    protected $signature = 'find';

    protected $description = 'Useful Tuti command to find other Tuti commands.';

    public function handle(): int
    {
        $commands = collect($this->getApplication()->all())
            ->keys()
            ->filter(fn (string $key): bool => $key !== $this->signature)
            ->map(fn ($key): int|string => $key)
            ->toArray();

        $command = suggest(
            'Which command do you want to run?',
            options: $commands,
            required: true,
            hint: 'Insert part of the command name to filter the list.'
        );

        $this->call($command);

        return self::SUCCESS;
    }
}
