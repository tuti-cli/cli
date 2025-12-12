<?php

declare(strict_types=1);

namespace App\Traits;

use function Termwind\render;

trait HasConsoleViewComponentsTrait
{
    private function welcomeBanner(): void
    {
        $this->newLine();

        // Top Welcome Box
        $this->line('<fg=yellow> ╭──────────────────────────────────────────────────────────────╮</>');
        $this->line('<fg=yellow> │                     Welcome to TUTI!                         │</>');
        $this->line('<fg=yellow> ╰──────────────────────────────────────────────────────────────╯</>');

        $this->newLine();

        // ASCII Logo
        $this->line('<fg=bright-yellow>             ████████╗ ██╗   ██╗ ████████╗ ██╗</>');
        $this->line('<fg=bright-yellow>             ╚══██╔══╝ ██║   ██║ ╚══██╔══╝ ██║</>');
        $this->line('<fg=bright-yellow>                ██║    ██║   ██║    ██║    ██║</>');
        $this->line('<fg=bright-yellow>                ██║    ██║   ██║    ██║    ██║</>');
        $this->line('<fg=bright-yellow>                ██║    ╚██████╔╝    ██║    ██║</>');
        $this->line('<fg=bright-yellow>                ╚═╝     ╚═════╝     ╚═╝    ╚═╝</>');

        $this->newLine();

        // Subtitle
        $this->line('<fg=gray>             Total Universal Tool Infrastructure</>');

        $this->newLine();

        // Description
        $this->line('<fg=magenta;options=bold>  Managing single or multiple environments with confidence.</>');
        $this->line('<fg=magenta;options=bold>             Deploy anywhere, manage everything.</>');

        $tutiVersion = config('app.version');

        render('
            <div class="pl-13 pt-1">
                <span>Version: ' . $tutiVersion . '</span> | <a href="https://tuti.cli">Documentation</a>
            </div>
        ');

        $this->newLine();
    }

    private function dividerDouble(string $color = 'gray', int $width = 60): void
    {
        $this->line(' <fg=' . $color . '>' . str_repeat('═', $width) . '</>');
    }

    private function dividerWithText(string $text, string $color = 'bright-cyan', int $width = 60): void
    {
        $textLength = mb_strlen($text);
        $padding = max(0, ($width - $textLength - 4) / 2);
        $leftLine = str_repeat('─', (int) floor($padding));
        $rightLine = str_repeat('─', (int) ceil($padding));

        $this->line(
            '<fg=' . $color . '>' . $leftLine .
            '</> <fg=white;options=bold>' . $text .
            '</> <fg=' . $color . '>' . $rightLine . '</>'
        );
    }
}
