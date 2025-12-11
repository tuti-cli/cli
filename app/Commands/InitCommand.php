<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\Tuti;
use App\Traits\HasConsoleViewComponentsTrait;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Termwind\render;

final class InitCommand extends Command
{
    use HasConsoleViewComponentsTrait;

    /* @var string */
    protected $signature = 'init {--force : Force reinitialization}';

    /* @var string */
    protected $description = 'Initialize Tuti CLI for the current project';

    public function handle(): int
    {
        if ($this->option('force')) {
            $choose = select(
                label: 'Force reinitialization will delete existing configuration. What would you like to do?',
                options: [
                    true => 'Reinitialize project',
                    false => 'Exit without changes',
                ],
                hint: 'Use [Enter] to select the action'
            );

            if ($choose) {
                $this->warn(' Reinitializing project...');

                if (is_dir(getcwd().'/.tuti')) {
                    $this->newLine();
                    $this->warn(' Removing existing .tuti directory...');

                    $deleteOldConfig = new Process(['rm', '-rf', getcwd().'/.tuti']);
                    $deleteOldConfig->run();

                    $this->info(' Existing configuration removed.');
                }
            } else {
                $this->info(' Exiting without changes.');
                return self::SUCCESS;
            }
        }

        $this->welcomeBanner();

        if (is_tuti_exists()) {
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <div class="px-2 py-1 bg-yellow-500 flex justify-between">
                        <span class="text-black font-bold">⚠ WARNING:</span>
                        <span class="text-black">Project already initialized!</span>
                    </div>
                </div>
            HTML
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->newLine();
        $this->newLine();

        /*
         *
         * TODO: Detect more project details
         * Detected Project Details:
         * +----------------+------------------+
         * | Property       | Value            |
         * +----------------+------------------+
         * | Detected       | ✓                |
         * | Project Type   | Laravel          |
         * | Environment    | Local            |
         * | PHP Version    | 8.3              |
         * +----------------+------------------+
        */
        $this->warn(' Detecting project type...');
        $projectType = spin(
            callback: $this->detectProjectType(...),
            message: 'Detecting project type...'
        );

        table(
            headers: ['Detected', 'Project Type'],
            rows: [['✓', $projectType]],
        );

        // Get project info
        $randomName = sprintf('%s-%s', basename(getcwd()), random_int(100, 999));

        $projectName = text(
            label: 'What is the name of your project?',
            placeholder: 'my-awesome-app',
            default: $randomName,
            required: 'Project name is required',
            hint: 'Used for naming containers and networks',
            transform: fn ($value) => mb_strtolower(str_replace(' ', '-', $value))
        );

        $this->createConfigStructure($projectName, $projectType);

        // Success screen
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-2 py-1 bg-green-600">
                    <div class="flex justify-between">
                        <span class="text-black font-bold">✓ SUCCESS!</span>
                        <span class="text-black">Project initialized</span>
                    </div>
                </div>
            </div>
        HTML
        );

        $nextStep = select(
            label: 'What would you like to do next?',
            options: [
                'tuti local:start' => 'Start local environment',
                'tuti env:local' => 'Edit local environment settings',
                'exit' => 'Exit',
            ],
            default: 'tuti local:start',
            hint: 'Use [Enter] to select the action'
        );

        if ($nextStep === 'exit') {
            return self::SUCCESS;
        }

        $this->call($nextStep);

        return self::SUCCESS;
    }

    private function detectProjectType(): string
    {

        if (file_exists(getcwd().'/composer.json')) {
            $composer = json_decode(
                file_get_contents(getcwd().'/composer.json'),
                true
            );

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($composer)) {
                return 'generic';
            }

            if (isset($composer['require']['laravel/framework'])) {
                return 'laravel';
            }

            if (isset($composer['require']['laravel-zero/framework'])) {
                return 'laravel-zero';
            }

            return 'php';
        }

        if (file_exists(getcwd().'/package.json')) {
            return 'node-js';
        }

        return 'generic';
    }

    private function createConfigStructure(string $name, string $type): void
    {
        $steps = [
            '.tuti directory' => fn (): bool => mkdir(getcwd().'/.tuti'),
            'environments directory' => fn (): bool => mkdir(getcwd().'/.tuti/environments'),
            'docker directory' => fn (): bool => mkdir(getcwd().'/.tuti/docker'),
            'config.yml file' => fn () => $this->generateConfig($name, $type),
            'docker-compose.yml file' => fn () => $this->generateDockerCompose($type),
        ];

        $progress = progress(
            label: '⚙ Configuring project...',
            steps: count($steps),
            hint: 'Please wait while TUTI set up your environment...'
        );

        $messages = [];

        $progress->start();

        foreach ($steps as $label => $callback) {
            $progress->label("⚙ Creating {$label}");

            $callback();
            $progress->advance();

            $messages[] = "Created {$label}";
        }

        $progress->label('Done! Project is ready.');
        $progress->finish();

        foreach ($messages as $m) {
            $this->renderProgressStep($m);
        }
    }

    private function renderProgressStep(string $message): void
    {
        render(<<<HTML
            <div class="mx-2">
                <span class="text-green-500">✓</span>
                <span class="text-gray-200 ml-1">{$message}</span>
            </div>
        HTML
        );
    }

    private function generateConfig(string $name, string $type): void
    {
        $config = [
            'project' => [
                'name' => $name,
                'type' => mb_strtolower($type),
                'version' => '1.0.0',
            ],
            'environments' => [
                'local' => [
                    'type' => 'docker',
                    'services' => $this->getDefaultServices(mb_strtolower($type)),
                ],
                'staging' => [
                    'type' => 'remote',
                    'host' => '',
                ],
                'production' => [
                    'type' => 'remote',
                    'host' => '',
                ],
            ],
        ];

        $yaml = Yaml::dump($config);

        $result = file_put_contents(
            getcwd().'/.tuti/config.yml',
            $yaml
        );

        if ($result === false) {
            throw new RuntimeException('Failed to write config.yml. Check file permissions.');
        }
    }

    private function getDefaultServices(string $type): array
    {
        return match ($type) {
            'laravel' => ['mysql', 'redis', 'mailhog'],
            'node.js' => ['postgres', 'redis'],
            default => [],
        };
    }

    private function generateDockerCompose(string $type): void
    {
        // Docker compose generation logic
        $template = $this->getDockerComposeTemplate(mb_strtolower($type));
        $result = file_put_contents(
            getcwd().'/.tuti/docker/docker-compose.yml',
            $template
        );

        if ($result === false) {
            throw new RuntimeException('Failed to write docker-compose.yml. Check file permissions.');
        }
    }

    private function getDockerComposeTemplate(string $type): string
    {
        if ($type === 'laravel' || $type === 'laravel-zero') {
            return <<<'YAML'
services:
  app:
    image: serversideup/php:8.4-cli
    container_name: ${PROJECT_NAME:-app}_app
    working_dir: /var/www
    volumes:
      - ../../:/var/www
    networks:
      - app-network

#  mysql:
#    image: mysql:8.0
#    container_name: ${PROJECT_NAME:-app}_mysql
#    environment:
#      MYSQL_ROOT_PASSWORD: root
#      MYSQL_DATABASE: ${DB_DATABASE:-laravel}
#    ports:
#      - "${DB_PORT:-3306}:3306"
#    volumes:
#      - mysql-data:/var/lib/mysql
#    networks:
#      - app-network

#  redis:
#    image: redis:7-alpine
#    container_name: ${PROJECT_NAME:-app}_redis
#    ports:
#      - "${REDIS_PORT:-6379}:6379"
#    networks:
#      - app-network
#
#  mailhog:
#    image: mailhog/mailhog
#    container_name: ${PROJECT_NAME:-app}_mailhog
#    ports:
#      - "1025:1025"
#      - "8025:8025"
#    networks:
#      - app-network

networks:
  app-network:
    driver: bridge

#  volumes:
#  mysql-data:
YAML;
        }

        return '';
    }
}
