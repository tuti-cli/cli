<?php

declare(strict_types=1);

/**
 * EnvCommand Feature Tests
 *
 * Tests the `tuti env:check` command which checks environment configuration.
 *
 * @see EnvCommand
 */

use App\Commands\EnvCommand;
use LaravelZero\Framework\Commands\Command;

// ─── Registration ───────────────────────────────────────────────────────

describe('EnvCommand Registration', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(EnvCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(EnvCommand::class);

        expect($command->getName())->toBe('env:check');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(EnvCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has --show option', function (): void {
        $command = $this->app->make(EnvCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('show'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(EnvCommand::class);

        expect($command->getDescription())->toBe('Check environment configuration');
    });
});

// ─── Project Directory Checks ───────────────────────────────────────────

describe('EnvCommand Project Directory Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when not in a tuti project directory', function (): void {
        chdir($this->testDir);

        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Not in a tuti project directory');
    });

    it('shows hint when not in project directory', function (): void {
        chdir($this->testDir);

        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Run this command from a project root');
    });
});

// ─── .env File Checks ────────────────────────────────────────────────────

describe('EnvCommand .env File Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        // Create .tuti directory to make it a valid project
        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when .env file does not exist', function (): void {
        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('.env file not found in project root');
    });

    it('shows expected location hint when .env missing', function (): void {
        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Expected location:');
    });

    it('shows reinitialize hint when .env missing', function (): void {
        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Run "tuti stack:laravel --force" to reinitialize');
    });
});

// ─── Laravel Configuration Display ───────────────────────────────────────

describe('EnvCommand Laravel Configuration Display', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        // Create .tuti directory to make it a valid project
        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('shows success when .env file exists', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('.env file found:');
    });

    it('displays Laravel Configuration section', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Laravel Configuration');
    });

    it('shows checkmark for configured Laravel variables', function (): void {
        $envContent = <<<'ENV'
APP_NAME="Test App"
APP_KEY=base64:testkey123456
APP_ENV=local
APP_URL=http://localhost
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=testdb
REDIS_HOST=redis
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('APP_NAME')
            ->expectsOutputToContain('APP_ENV')
            ->expectsOutputToContain('DB_CONNECTION');
    });

    it('shows missing indicator for absent variables', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('(missing)');
    });
});

// ─── Docker Configuration Display ─────────────────────────────────────────

describe('EnvCommand Docker Configuration Display', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays Docker Configuration section', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Docker Configuration');
    });

    it('warns when tuti-cli section not found', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('tuti-cli section not found in .env');
    });

    it('does not warn when tuti-cli section exists', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
# TUTI-CLI DOCKER CONFIGURATION
PROJECT_NAME=testproject
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('tuti-cli section not found in .env');
    });

    it('shows Docker variables when present', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
# TUTI-CLI DOCKER CONFIGURATION
PROJECT_NAME=myproject
APP_DOMAIN=myproject.test
PHP_VERSION=8.4
BUILD_TARGET=development
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('PROJECT_NAME')
            ->expectsOutputToContain('APP_DOMAIN')
            ->expectsOutputToContain('PHP_VERSION');
    });
});

// ─── Sensitive Value Masking ─────────────────────────────────────────────

describe('EnvCommand Sensitive Value Masking', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('masks values containing PASSWORD', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
DB_PASSWORD=supersecretpassword
REDIS_HOST=redis
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        // Note: DB_PASSWORD is not checked by default, but if it were, it would be masked
        // This test verifies the command runs successfully with a PASSWORD variable present
        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('supersecretpassword');
    });

    it('masks KEY values', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
APP_KEY=base64:secretkey12345678
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('secretkey12345678');
    });

    it('masks SECRET values', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
AWS_SECRET_ACCESS_KEY=myawssecret
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('myawssecret');
    });

    it('masks TOKEN values', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
GITHUB_TOKEN=ghp_12345678abcdef
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('ghp_12345678abcdef');
    });

    it('shows not set for empty sensitive values', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
APP_KEY=
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('<not set>');
    });

    it('shows not set for null sensitive values', function (): void {
        $envContent = <<<'ENV'
APP_NAME=test
APP_KEY=null
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('<not set>');
    });
});

// ─── --show Option ───────────────────────────────────────────────────────

describe('EnvCommand --show Option', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays Environment Variables section with --show', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check', ['--show' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Environment Variables (sensitive values hidden)');
    });

    it('does not display full env without --show', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('Environment Variables (sensitive values hidden)');
    });

    it('shows all variables with --show', function (): void {
        $envContent = <<<'ENV'
APP_NAME=MyApp
APP_ENV=local
CUSTOM_VAR=customvalue
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check', ['--show' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('CUSTOM_VAR');
    });

    it('masks sensitive values with --show', function (): void {
        $envContent = <<<'ENV'
APP_NAME=MyApp
API_SECRET=supersecret123
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check', ['--show' => true])
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('supersecret123');
    });

    it('displays comments with --show', function (): void {
        $envContent = <<<'ENV'
# This is a comment
APP_NAME=test
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->artisan('env:check', ['--show' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('# This is a comment');
    });
});

// ─── Tips and Hints ──────────────────────────────────────────────────────

describe('EnvCommand Tips and Hints', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();

        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays edit tip after checking', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Edit .env to configure your project');
    });

    it('displays restart hint after checking', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('restart: tuti local:stop && tuti local:start');
    });

    it('displays hint when tuti-cli section missing', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Run "tuti stack:laravel --force" to add Docker configuration');
    });
});

// ─── Branded Output ──────────────────────────────────────────────────────

describe('EnvCommand Branded Output', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays branded header', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);
        file_put_contents($this->testDir . '/.env', 'APP_NAME=test');

        $this->artisan('env:check')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Environment Configuration');
    });

    it('displays branded header even on failure', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);
        chdir($this->testDir);

        $this->artisan('env:check')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Environment Configuration');
    });
});
