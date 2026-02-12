<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use LaravelZero\Framework\Commands\Command;

final class UIShowcaseCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'ui:showcase';

    protected $description = 'Showcase all branded output components';

    public function handle(): int
    {
        // ═══════════════════════════════════════════════════════════
        // Welcome & Branding
        // ═══════════════════════════════════════════════════════════

        $this->welcomeBanner();

        // Or use branded header for features:
        // $this->brandedHeader('Stack Installation', 'my-laravel-app');

        // ═══════════════════════════════════════════════════════════
        // Status Messages - Clear feedback
        // ═══════════════════════════════════════════════════════════

        $this->section('Status Messages');

        $this->success('Operation completed successfully');
        $this->failure('An error occurred during operation');
        $this->warning('This action cannot be undone');
        $this->note('Make sure Docker is running');
        $this->hint('Use --force to skip confirmation prompts');
        $this->waiting('Downloading stack templates');
        $this->done('Configuration loaded');
        $this->skipped('Database migration');

        // ═══════════════════════════════════════════════════════════
        // Action Messages - What's happening
        // ═══════════════════════════════════════════════════════════

        $this->section('Action Messages');

        $this->taskStart('Installing dependencies');
        $this->action('Pulling Docker images', 'nginx:latest');
        $this->command('docker-compose up -d');
        $this->taskDone('Dependencies installed');
        $this->taskFailed('Cache warm-up failed');

        // ═══════════════════════════════════════════════════════════
        // Progress & Steps - Multi-step operations
        // ═══════════════════════════════════════════════════════════

        $this->section('Progress & Steps');

        $this->step(1, 4, 'Validating configuration');
        $this->step(2, 4, 'Creating Docker network');
        $this->step(3, 4, 'Starting services');
        $this->step(4, 4, 'Running health checks');

        $this->spacer();

        $this->header('Services to Install');
        $this->bullet('PostgreSQL 15', 'green');
        $this->bullet('Redis 7', 'cyan');
        $this->bullet('Nginx', 'yellow');
        $this->subItem('With SSL certificates');

        // ═══════════════════════════════════════════════════════════
        // File Operations - Created/Modified/Deleted
        // ═══════════════════════════════════════════════════════════

        $this->section('File Operations');

        $this->created('.tuti/docker-compose.yml');
        $this->created('.tuti/config.json');
        $this->modified('.env');
        $this->deleted('.tuti/cache/old-config.json');
        $this->unchanged('composer.json');

        // ═══════════════════════════════════════════════════════════
        // Callout Boxes - Important information
        // ═══════════════════════════════════════════════════════════

        $this->section('Callout Boxes');

        $this->successBox('Installation Complete', [
            'All services are running',
            'Health checks passed',
        ]);

        $this->warningBox('Attention Required', [
            'Port 3306 is already in use',
            'MySQL will use port 3307 instead',
        ]);

        $this->tipBox('Pro Tip', [
            'Run "tuti local:logs" to view service logs',
            'Use "tuti local:shell" to access containers',
        ]);

        // ═══════════════════════════════════════════════════════════
        // Key-Value Display - Configuration info
        // ═══════════════════════════════════════════════════════════

        $this->section('Configuration Display');

        $this->keyValueList([
            'Project' => 'my-laravel-app',
            'Stack' => 'Laravel',
            'PHP Version' => '8.4',
            'Database' => 'PostgreSQL 15',
            'Cache' => 'Redis 7',
        ]);

        $this->spacer();
        $this->labeledValue('Environment', 'Development');
        $this->labeledValue('Status', 'Running');

        // ═══════════════════════════════════════════════════════════
        // Boxes & Panels - Grouped information
        // ═══════════════════════════════════════════════════════════

        $this->section('Boxes & Panels');

        $this->infoBox([
            'Project' => 'MyProject',
            'Stack' => 'Laravel',
            'PHP' => '8.4',
        ]);

        $this->spacer();

        $this->panel([
            'Application URL: ' . $this->highlight('http://localhost:8080'),
            'Database Port:   ' . $this->highlight('5432'),
            'Redis Port:      ' . $this->highlight('6379'),
        ]);

        // ═══════════════════════════════════════════════════════════
        // Badges - Inline status indicators
        // ═══════════════════════════════════════════════════════════

        $this->section('Badge Variants');

        $this->line('  Default:  ' . $this->badge(' TUTI '));
        $this->line('  Success:  ' . $this->badgeSuccess('RUNNING'));
        $this->line('  Error:    ' . $this->badgeError('STOPPED'));
        $this->line('  Warning:  ' . $this->badgeWarning('UPDATING'));
        $this->line('  Info:     ' . $this->badgeInfo('NEW'));

        // ═══════════════════════════════════════════════════════════
        // Final Summary - Completion with next steps
        // ═══════════════════════════════════════════════════════════

        $this->completed('Stack initialization finished!', [
            'cd my-laravel-app',
            'tuti local:start',
            'Open http://localhost:8080',
        ]);

        // Or for failures:
        $this->failed('Could not start services', [
            'Check if Docker is running',
            'Verify port availability',
            'Run with --verbose for details',
        ]);

        // ═══════════════════════════════════════════════════════════
        // Outro - Final branded message
        // ═══════════════════════════════════════════════════════════

        // With link
        //        $this->outro('Thank you for using TUTI!', 'Documentation', 'https://tuti.cli');

        // Without link (text only, centered)
        $this->outro('Thank you for using TUTI!');

        return Command::SUCCESS;
    }
}
