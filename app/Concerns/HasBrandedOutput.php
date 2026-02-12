<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\Theme;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;

/**
 * Trait for branded console UI components.
 *
 * Provides theming, logos, banners, messages, and formatting helpers for Tuti CLI commands.
 * Creates a consistent, beautiful UX/UI experience across all CLI interactions.
 *
 * @property OutputInterface $output
 *
 * @method void line(string $string, string|null $style = null, int|string|null $verbosity = null)
 * @method void newLine(int $count = 1)
 */
trait HasBrandedOutput
{
    protected ?Theme $theme = null;

    // ═══════════════════════════════════════════════════════════════
    // Theme Management
    // ═══════════════════════════════════════════════════════════════

    protected function initTheme(?Theme $theme = null): void
    {
        $this->theme = $theme ?? Theme::random();
    }

    protected function getTheme(): Theme
    {
        if ($this->theme === null) {
            $this->initTheme();
        }

        return $this->theme;
    }

    // ═══════════════════════════════════════════════════════════════
    // Branding & Headers
    // ═══════════════════════════════════════════════════════════════

    protected function brandedHeader(string $featureName, ?string $projectName = null, ?Theme $theme = null): void
    {
        $this->newLine();
        $this->initTheme($theme);
        $this->tutiLogo();
        $this->tagline($featureName);
        $this->newLine();

        if ($projectName !== null) {
            $this->newLine();
            $this->line("  <fg=gray>→</> Working with {$this->badge($projectName)}");
            $this->newLine();
        }
    }

    protected function welcomeBanner(?Theme $theme = null): void
    {
        $this->initTheme($theme);

        $this->newLine();

        $primary = $this->getTheme()->primary();
        $this->output->writeln($this->ansi256Fg($primary, ' ╭──────────────────────────────────────────────────────────────╮'));
        $this->output->writeln($this->ansi256Fg($primary, ' │                     Welcome to TUTI!                         │'));
        $this->output->writeln($this->ansi256Fg($primary, ' ╰──────────────────────────────────────────────────────────────╯'));

        $this->newLine();
        $this->tutiLogo();

        $this->line('<fg=gray>             Total Universal Tool Infrastructure</>');
        $this->newLine();

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

    protected function tutiLogo(): void
    {
        $lines = [
            '             ████████╗ ██╗   ██╗ ████████╗ ██╗',
            '             ╚══██╔══╝ ██║   ██║ ╚══██╔══╝ ██║',
            '                ██║    ██║   ██║    ██║    ██║',
            '                ██║    ██║   ██║    ██║    ██║',
            '                ██║    ╚██████╔╝    ██║    ██║',
            '                ╚═╝     ╚═════╝     ╚═╝    ╚═╝',
        ];

        $gradient = $this->getTheme()->gradient();

        foreach ($lines as $index => $line) {
            $this->output->writeln($this->ansi256Fg($gradient[$index], $line));
        }

        $this->newLine();
    }

    protected function tagline(string $featureName): void
    {
        $tagline = "✦ TUTI :: {$featureName} :: Environments Made Simple ✦ ";
        $this->output->writeln('  ' . $this->badge($tagline));
    }

    protected function outro(string $text, ?string $linkLabel = null, ?string $linkUrl = null, int $terminalWidth = 80): void
    {
        $this->newLine();

        $primary = $this->getTheme()->primary();

        // Calculate full text length for centering
        $fullText = $text;
        if ($linkLabel !== null) {
            $fullText .= ' | ' . $linkLabel;
        }

        $textLength = mb_strlen($fullText);
        $paddingLength = (int) (floor(($terminalWidth - $textLength) / 2));
        $rightPadding = $terminalWidth - $textLength - $paddingLength;

        $leftPad = str_repeat(' ', max(0, $paddingLength));
        $rightPad = str_repeat(' ', max(0, $rightPadding));

        if ($linkLabel !== null && $linkUrl !== null) {
            // With link: text + clickable link on same line, all with background
            // OSC 8 hyperlink format
            $hyperlink = "\033]8;;{$linkUrl}\033\\{$linkLabel}\033]8;;\033\\";

            $this->output->writeln(
                "\e[48;5;{$primary}m\e[30m\e[1m{$leftPad}{$text} | {$hyperlink}{$rightPad}\e[0m"
            );
        } else {
            // Without link: just centered text with background
            $this->output->writeln(
                "\e[48;5;{$primary}m\e[30m\e[1m{$leftPad}{$text}{$rightPad}\e[0m"
            );
        }

        $this->newLine();
    }

    // ═══════════════════════════════════════════════════════════════
    // Status Messages - Clear visual feedback for users
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a success message with checkmark.
     * Use when an operation completed successfully.
     */
    protected function success(string $message): void
    {
        $this->output->writeln("  <fg=green>✔</> {$message}");
    }

    /**
     * Display an error message with X mark.
     * Use when an operation failed.
     */
    protected function failure(string $message): void
    {
        $this->output->writeln("  <fg=red>✖</> {$message}");
    }

    /**
     * Display a warning message with warning symbol.
     * Use when user attention is needed.
     */
    protected function warning(string $message): void
    {
        $this->output->writeln("  <fg=yellow>⚠</> {$message}");
    }

    /**
     * Display an info/note message with arrow.
     * Use for general information or tips.
     */
    protected function note(string $message): void
    {
        $this->output->writeln("  <fg=blue>→</> {$message}");
    }

    /**
     * Display a hint message with lightbulb.
     * Use for helpful suggestions.
     */
    protected function hint(string $message): void
    {
        $this->output->writeln("  <fg=cyan>💡</> <fg=gray>{$message}</>");
    }

    /**
     * Display a waiting/processing message with dots.
     * Use when something is in progress.
     */
    protected function waiting(string $message): void
    {
        $this->output->writeln("  <fg=gray>◌</> {$message}<fg=gray>...</>");
    }

    /**
     * Display a done/completed message.
     * Use to confirm a step is finished.
     */
    protected function done(string $message = 'Done'): void
    {
        $this->output->writeln("  <fg=green>●</> {$message}");
    }

    /**
     * Display a skipped action message.
     * Use when an action was intentionally skipped.
     */
    protected function skipped(string $message): void
    {
        $this->output->writeln("  <fg=gray>○</> <fg=gray>{$message} (skipped)</>");
    }

    // ═══════════════════════════════════════════════════════════════
    // Action Messages - Guide user through operations
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a task being started.
     * Use at the beginning of a discrete task.
     */
    protected function taskStart(string $task): void
    {
        $this->output->writeln("  <fg=white>▶</> {$task}");
    }

    /**
     * Display a task completed.
     * Use when a discrete task finishes.
     */
    protected function taskDone(string $task): void
    {
        $this->output->writeln("  <fg=green>✔</> {$task}");
    }

    /**
     * Display a task failed.
     * Use when a discrete task fails.
     */
    protected function taskFailed(string $task): void
    {
        $this->output->writeln("  <fg=red>✖</> {$task}");
    }

    /**
     * Display an action the CLI is performing.
     * Use to show what's happening behind the scenes.
     */
    protected function action(string $action, ?string $detail = null): void
    {
        $detailText = $detail ? " <fg=gray>{$detail}</>" : '';
        $this->output->writeln("  <fg=cyan>⟳</> {$action}{$detailText}");
    }

    /**
     * Display a command that will be or was executed.
     * Use to show shell commands being run.
     */
    protected function command(string $command): void
    {
        $this->output->writeln("  <fg=gray>\$</> <fg=white>{$command}</>");
    }

    // ═══════════════════════════════════════════════════════════════
    // Progress & Steps - Multi-step operation tracking
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a numbered step in a process.
     * Use for multi-step operations.
     */
    protected function step(int $current, int $total, string $description): void
    {
        $badge = $this->stepBadge($current, $total);
        $this->output->writeln("  {$badge} {$description}");
    }

    /**
     * Get a step badge string (for inline use).
     */
    protected function stepBadge(int $step, int $total): string
    {
        return $this->badge(" {$step}/{$total} ");
    }

    /**
     * Display a bullet point item.
     * Use for lists of items.
     */
    protected function bullet(string $text, string $color = 'white'): void
    {
        $primary = $this->getTheme()->primary();
        $bullet = $this->ansi256Fg($primary, '•');
        $this->output->writeln("  {$bullet} <fg={$color}>{$text}</>");
    }

    /**
     * Display an indented sub-item.
     * Use for nested information.
     */
    protected function subItem(string $text): void
    {
        $this->output->writeln("      <fg=gray>└─</> {$text}");
    }

    // ═══════════════════════════════════════════════════════════════
    // Section Headers - Organize output into logical sections
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a section header.
     * Use to start a new logical section.
     */
    protected function section(string $title): void
    {
        $this->newLine();
        $this->dividerWithText($title);
        $this->newLine();
    }

    /**
     * Display a subsection header.
     * Use for nested sections.
     */
    protected function subsection(string $title): void
    {
        $this->newLine();
        $color = $this->getTheme()->accent();
        $this->output->writeln('  ' . $this->ansi256Fg($color, "┌─ {$title}"));
    }

    /**
     * Display a mini header (bold text).
     * Use for small groupings.
     */
    protected function header(string $text): void
    {
        $this->newLine();
        $this->output->writeln("  <fg=white;options=bold>{$text}</>");
    }

    // ═══════════════════════════════════════════════════════════════
    // Dividers - Visual separation
    // ═══════════════════════════════════════════════════════════════

    protected function divider(string $char = '─', int $width = 60): void
    {
        $color = $this->getTheme()->primary();
        $this->output->writeln(' ' . $this->ansi256Fg($color, str_repeat($char, $width)));
    }

    protected function dividerDouble(int $width = 60): void
    {
        $this->divider('═', $width);
    }

    protected function dividerLight(int $width = 60): void
    {
        $this->output->writeln(' ' . $this->ansi256Fg(240, str_repeat('┄', $width)));
    }

    protected function dividerWithText(string $text, int $width = 60): void
    {
        $textLength = mb_strlen($text);
        $padding = max(0, ($width - $textLength - 4) / 2);
        $leftLine = str_repeat('─', (int) floor($padding));
        $rightLine = str_repeat('─', (int) ceil($padding));

        $color = $this->getTheme()->primary();
        $this->output->writeln(
            ' ' . $this->ansi256Fg($color, $leftLine) .
            ' <fg=white;options=bold>' . $text . '</> ' .
            $this->ansi256Fg($color, $rightLine)
        );
    }

    protected function spacer(): void
    {
        $this->newLine();
    }

    // ═══════════════════════════════════════════════════════════════
    // Callout Boxes - Important information blocks
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a success callout box.
     *
     * @param  array<string>  $lines
     */
    protected function successBox(string $title, array $lines = []): void
    {
        $this->calloutBox($title, $lines, 'green', '✔');
    }

    /**
     * Display an error callout box.
     *
     * @param  array<string>  $lines
     */
    protected function errorBox(string $title, array $lines = []): void
    {
        $this->calloutBox($title, $lines, 'red', '✖');
    }

    /**
     * Display a warning callout box.
     *
     * @param  array<string>  $lines
     */
    protected function warningBox(string $title, array $lines = []): void
    {
        $this->calloutBox($title, $lines, 'yellow', '⚠');
    }

    /**
     * Display an info callout box.
     *
     * @param  array<string, string>  $items
     */
    protected function infoBox(array $items, int $width = 60): void
    {
        $this->box('Info', $items, $width, true);
    }

    /**
     * Display a tip/hint callout box.
     *
     * @param  array<string>  $lines
     */
    protected function tipBox(string $title, array $lines = []): void
    {
        $this->calloutBox($title, $lines, 'cyan', '💡');
    }

    /**
     * Generic callout box with icon and color.
     *
     * @param  array<string>  $lines
     */
    protected function calloutBox(string $title, array $lines, string $color, string $icon): void
    {
        $this->newLine();
        $this->output->writeln("  <fg={$color}>{$icon} {$title}</>");

        foreach ($lines as $line) {
            $this->output->writeln("    <fg=gray>{$line}</>");
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Box Drawing - Bordered containers
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a bordered box with title and content.
     * Can handle both plain text lines and key-value pairs.
     *
     * @param  array<int|string, string>  $lines
     */
    protected function box(string $title, array $lines, int $width = 60, bool $isKeyValue = false): void
    {
        $color = $this->getTheme()->primary();

        // Top border: ╭─ Title ───────────────────────────────────────╮
        // Structure: ╭ (1) + ─ (1) + " Title " (title+2) + ─── (fill) + ╮ (1) = width
        $titleWithSpaces = " {$title} ";
        $titleLen = mb_strlen($titleWithSpaces);
        $topFill = $width - 2 - $titleLen - 1; // -2 for ╭─, -1 for ╮

        $this->output->writeln(
            ' ' . $this->ansi256Fg($color, '╭─') .
            $titleWithSpaces .
            $this->ansi256Fg($color, str_repeat('─', max(0, $topFill)) . '╮')
        );

        // Content lines: │ content                                    │
        // Structure: │ (1) + space (1) + content + padding + space (1) + │ (1) = width
        $contentWidth = $width - 4; // space for "│ " and " │"

        foreach ($lines as $key => $line) {
            if ($isKeyValue) {
                $line = "<fg=gray>{$key}:</> <fg=white>{$line}</>";
            }

            // Strip formatting codes to get real visible length
            $cleanLine = preg_replace('/\x1b\[[0-9;]*m|<[^>]*>/', '', (string) $line) ?? (string) $line;
            $lineLen = mb_strlen($cleanLine);
            $rightPad = max(0, $contentWidth - $lineLen);

            $this->output->writeln(
                ' ' . $this->ansi256Fg($color, '│') .
                ' ' . $line . str_repeat(' ', $rightPad) .
                ' ' . $this->ansi256Fg($color, '│')
            );
        }

        // Bottom border: ╰───────────────────────────────────────────╯
        // Structure: ╰ (1) + ─── (fill) + ╯ (1) = width
        $bottomFill = $width - 2;
        $this->output->writeln(' ' . $this->ansi256Fg($color, '╰' . str_repeat('─', $bottomFill) . '╯'));
    }

    /**
     * Display a simple bordered panel.
     *
     * @param  array<string>  $lines
     */
    protected function panel(array $lines, int $width = 60): void
    {
        $color = $this->getTheme()->primary();

        // Top border
        $this->output->writeln(' ' . $this->ansi256Fg($color, '┌' . str_repeat('─', $width - 2) . '┐'));

        // Content lines
        foreach ($lines as $line) {
            // Strip ANSI codes to get real length
            $cleanLine = preg_replace('/\x1b\[[0-9;]*m/', '', (string) $line) ?? $line;
            $lineLen = mb_strlen((string) $cleanLine);
            $rightPad = max(0, $width - $lineLen - 4);

            $this->output->writeln(
                ' ' . $this->ansi256Fg($color, '│') .
                ' ' . $line . str_repeat(' ', $rightPad) .
                ' ' . $this->ansi256Fg($color, '│')
            );
        }

        // Bottom border
        $this->output->writeln(' ' . $this->ansi256Fg($color, '└' . str_repeat('─', $width - 2) . '┘'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Key-Value Display - Configuration and data display
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a key-value pair.
     */
    protected function keyValue(string $key, string $value, int $keyWidth = 20): void
    {
        $paddedKey = mb_str_pad($key, $keyWidth);
        $this->output->writeln("  <fg=gray>{$paddedKey}</> {$value}");
    }

    /**
     * Display multiple key-value pairs.
     *
     * @param  array<string, string|int>  $items
     */
    protected function keyValueList(array $items, int $keyWidth = 20): void
    {
        foreach ($items as $key => $value) {
            $this->keyValue($key, (string) $value, $keyWidth);
        }
    }

    /**
     * Display a labeled value with badge.
     */
    protected function labeledValue(string $label, string $value): void
    {
        $this->output->writeln("  <fg=gray>{$label}:</> {$this->badge(" {$value} ")}");
    }

    // ═══════════════════════════════════════════════════════════════
    // Formatting Helpers - Text styling utilities
    // ═══════════════════════════════════════════════════════════════

    protected function ansi256Fg(int $color, string $text): string
    {
        return "\e[38;5;{$color}m{$text}\e[0m";
    }

    protected function ansi256Bg(int $color, string $text): string
    {
        return "\e[48;5;{$color}m{$text}\e[0m";
    }

    protected function badge(string $text): string
    {
        $primary = $this->getTheme()->primary();

        return "\e[48;5;{$primary}m\e[30m\e[1m{$text}\e[0m";
    }

    protected function badgeSuccess(string $text): string
    {
        return "\e[48;5;34m\e[97m\e[1m {$text} \e[0m";
    }

    protected function badgeError(string $text): string
    {
        return "\e[48;5;196m\e[97m\e[1m {$text} \e[0m";
    }

    protected function badgeWarning(string $text): string
    {
        return "\e[48;5;208m\e[30m\e[1m {$text} \e[0m";
    }

    protected function badgeInfo(string $text): string
    {
        return "\e[48;5;39m\e[97m\e[1m {$text} \e[0m";
    }

    protected function hyperlink(string $label, string $url): string
    {
        return "\033]8;;{$url}\007{$label}\033]8;;\033\\";
    }

    protected function highlight(string $text): string
    {
        $accent = $this->getTheme()->accent();

        return $this->ansi256Fg($accent, $text);
    }

    protected function dim(string $text): string
    {
        return "<fg=gray>{$text}</>";
    }

    protected function bold(string $text): string
    {
        return "<fg=white;options=bold>{$text}</>";
    }

    // ═══════════════════════════════════════════════════════════════
    // Final Messages - Completion and summary displays
    // ═══════════════════════════════════════════════════════════════

    /**
     * Display a final success summary.
     * Use at the end of successful operations.
     *
     * @param  array<string>  $nextSteps
     */
    protected function completed(string $message, array $nextSteps = []): void
    {
        $this->newLine();
        $this->output->writeln("  {$this->badgeSuccess('SUCCESS')} {$message}");

        if ($nextSteps !== []) {
            $this->newLine();
            $this->output->writeln('  <fg=gray>Next steps:</>');
            foreach ($nextSteps as $step) {
                $this->output->writeln("    <fg=cyan>→</> {$step}");
            }
        }

        $this->newLine();
    }

    /**
     * Display a final failure summary.
     * Use at the end of failed operations.
     *
     * @param  array<string>  $suggestions
     */
    protected function failed(string $message, array $suggestions = []): void
    {
        $this->newLine();
        $this->output->writeln("  {$this->badgeError('FAILED')} {$message}");

        if ($suggestions !== []) {
            $this->newLine();
            $this->output->writeln('  <fg=gray>Suggestions:</>');
            foreach ($suggestions as $suggestion) {
                $this->output->writeln("    <fg=yellow>→</> {$suggestion}");
            }
        }

        $this->newLine();
    }

    /**
     * Display what was created/generated.
     * Use to show files or resources created.
     */
    protected function created(string $path): void
    {
        $this->output->writeln("  <fg=green>+</> <fg=green>created</> {$path}");
    }

    /**
     * Display what was modified.
     */
    protected function modified(string $path): void
    {
        $this->output->writeln("  <fg=yellow>~</> <fg=yellow>modified</> {$path}");
    }

    /**
     * Display what was deleted.
     */
    protected function deleted(string $path): void
    {
        $this->output->writeln("  <fg=red>-</> <fg=red>deleted</> {$path}");
    }

    /**
     * Display what was unchanged.
     */
    protected function unchanged(string $path): void
    {
        $this->output->writeln("  <fg=gray>○</> <fg=gray>unchanged</> {$path}");
    }
}
