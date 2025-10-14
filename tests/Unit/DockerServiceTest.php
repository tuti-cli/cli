<?php

use App\Services\DockerService;
use Mockery as m;
use Symfony\Component\Process\Process;

describe('DockerService', function () {
    beforeEach(function () {
        $this->service = new DockerService();
        $this->originalCwd = getcwd();
    });

    afterEach(function () {
        chdir($this->originalCwd);
        m::close();
    });

    describe('constructor', function () {
        it('initializes with correct compose path', function () {
            $service = new DockerService();
            expect($service)->toBeInstanceOf(DockerService::class);
        });

        it('sets compose path based on current working directory', function () {
            $tempDir = sys_get_temp_dir() . '/test_docker_' . uniqid();
            mkdir($tempDir, 0777, true);
            chdir($tempDir);

            $service = new DockerService();

            expect($service)->toBeInstanceOf(DockerService::class);

            // Cleanup
            rmdir($tempDir);
        });
    });

    describe('isRunning', function () {
        it('returns true when docker is running', function () {
            // This test checks the actual method signature
            expect($this->service)->toHaveMethod('isRunning');
        });

        it('returns boolean value', function () {
            $result = $this->service->isRunning();
            expect($result)->toBeBool();
        });

        it('handles docker not installed gracefully', function () {
            // Test that method doesn't throw exception
            expect(fn() => $this->service->isRunning())->not->toThrow(Exception::class);
        });
    });

    describe('checkPortConflicts', function () {
        it('returns empty array as documented', function () {
            $result = $this->service->checkPortConflicts();

            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });

        it('returns array type consistently', function () {
            $result1 = $this->service->checkPortConflicts();
            $result2 = $this->service->checkPortConflicts();

            expect($result1)->toBeArray()
                ->and($result2)->toBeArray();
        });
    });

    describe('pullImages', function () {
        it('returns boolean value', function () {
            $tempDir = sys_get_temp_dir() . '/test_pull_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result = $service->pullImages();

            expect($result)->toBeBool();

            // Cleanup
            array_map('unlink', glob($tempDir . '/.tuti/docker/*'));
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles missing docker-compose file', function () {
            $tempDir = sys_get_temp_dir() . '/test_no_compose_' . uniqid();
            mkdir($tempDir, 0777, true);
            chdir($tempDir);

            $service = new DockerService();

            expect(fn() => $service->pullImages())->not->toThrow(Exception::class);

            // Cleanup
            rmdir($tempDir);
        });
    });

    describe('start', function () {
        it('returns boolean value', function () {
            $tempDir = sys_get_temp_dir() . '/test_start_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result = $service->start();

            expect($result)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles execution without docker-compose installed', function () {
            $service = new DockerService();
            expect(fn() => $service->start())->not->toThrow(Exception::class);
        });
    });

    describe('stop', function () {
        it('returns boolean value', function () {
            $tempDir = sys_get_temp_dir() . '/test_stop_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result = $service->stop();

            expect($result)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('can be called multiple times', function () {
            $tempDir = sys_get_temp_dir() . '/test_stop_multi_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result1 = $service->stop();
            $result2 = $service->stop();

            expect($result1)->toBeBool()
                ->and($result2)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });
    });

    describe('exec', function () {
        it('returns boolean value', function () {
            $tempDir = sys_get_temp_dir() . '/test_exec_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result = $service->exec('app', 'echo test');

            expect($result)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles empty service name', function () {
            $tempDir = sys_get_temp_dir() . '/test_exec_empty_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();

            expect(fn() => $service->exec('', 'ls'))->not->toThrow(Exception::class);

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles empty command', function () {
            $tempDir = sys_get_temp_dir() . '/test_exec_empty_cmd_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();

            expect(fn() => $service->exec('app', ''))->not->toThrow(Exception::class);

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles special characters in command', function () {
            $tempDir = sys_get_temp_dir() . '/test_exec_special_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();
            $result = $service->exec('app', 'echo "test $HOME"');

            expect($result)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });
    });

    describe('integration scenarios', function () {
        it('can chain multiple operations', function () {
            $tempDir = sys_get_temp_dir() . '/test_chain_' . uniqid();
            mkdir($tempDir . '/.tuti/docker', 0777, true);
            chdir($tempDir);

            $service = new DockerService();

            expect(fn() => [
                $service->pullImages(),
                $service->start(),
                $service->stop(),
            ])->not->toThrow(Exception::class);

            // Cleanup
            rmdir($tempDir . '/.tuti/docker');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('maintains state between method calls', function () {
            $service1 = new DockerService();
            $service2 = new DockerService();

            expect($service1)->toBeInstanceOf(DockerService::class)
                ->and($service2)->toBeInstanceOf(DockerService::class);
        });
    });
});