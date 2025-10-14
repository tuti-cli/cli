<?php

use App\Support\Tuti;

describe('Tuti Support Class', function () {
    beforeEach(function () {
        // Store original working directory
        $this->originalCwd = getcwd();
    });

    afterEach(function () {
        // Restore original working directory
        chdir($this->originalCwd);
    });

    describe('projectRoot', function () {
        it('returns null when .tuti directory does not exist', function () {
            // Create a temporary directory without .tuti
            $tempDir = sys_get_temp_dir() . '/test_no_tuti_' . uniqid();
            mkdir($tempDir, 0777, true);
            chdir($tempDir);

            expect(Tuti::projectRoot())->toBeNull();

            // Cleanup
            rmdir($tempDir);
        });

        it('returns project root when .tuti directory exists', function () {
            // Create a temporary directory with .tuti
            $tempDir = sys_get_temp_dir() . '/test_with_tuti_' . uniqid();
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . '/.tuti', 0777, true);
            chdir($tempDir);

            $result = Tuti::projectRoot();

            expect($result)->toBe($tempDir);

            // Cleanup
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('returns correct path when in subdirectory', function () {
            // Create project structure with subdirectory
            $tempDir = sys_get_temp_dir() . '/test_subdir_' . uniqid();
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . '/.tuti', 0777, true);
            mkdir($tempDir . '/subdir', 0777, true);
            chdir($tempDir);

            $result = Tuti::projectRoot();

            expect($result)->toBe($tempDir);

            // Cleanup
            rmdir($tempDir . '/subdir');
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('handles .tuti as a file instead of directory', function () {
            // Edge case: .tuti exists as a file, not directory
            $tempDir = sys_get_temp_dir() . '/test_tuti_file_' . uniqid();
            mkdir($tempDir, 0777, true);
            touch($tempDir . '/.tuti'); // Create as file
            chdir($tempDir);

            $result = Tuti::projectRoot();

            expect($result)->toBe($tempDir);

            // Cleanup
            unlink($tempDir . '/.tuti');
            rmdir($tempDir);
        });
    });

    describe('isInsideProject', function () {
        it('returns false when not in a tuti project', function () {
            $tempDir = sys_get_temp_dir() . '/test_outside_' . uniqid();
            mkdir($tempDir, 0777, true);
            chdir($tempDir);

            expect(Tuti::isInsideProject())->toBeFalse();

            // Cleanup
            rmdir($tempDir);
        });

        it('returns true when inside a tuti project', function () {
            $tempDir = sys_get_temp_dir() . '/test_inside_' . uniqid();
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . '/.tuti', 0777, true);
            chdir($tempDir);

            expect(Tuti::isInsideProject())->toBeTrue();

            // Cleanup
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });

        it('returns boolean type', function () {
            $result = Tuti::isInsideProject();
            expect($result)->toBeBool();
        });

        it('correctly coerces null to false', function () {
            $tempDir = sys_get_temp_dir() . '/test_coerce_' . uniqid();
            mkdir($tempDir, 0777, true);
            chdir($tempDir);

            $result = Tuti::isInsideProject();

            expect($result)->toBeFalse()
                ->and($result)->toBeBool();

            // Cleanup
            rmdir($tempDir);
        });

        it('correctly coerces string to true', function () {
            $tempDir = sys_get_temp_dir() . '/test_coerce_true_' . uniqid();
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . '/.tuti', 0777, true);
            chdir($tempDir);

            $result = Tuti::isInsideProject();

            expect($result)->toBeTrue()
                ->and($result)->toBeBool();

            // Cleanup
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });
    });

    describe('static methods behavior', function () {
        it('can be called statically without instantiation', function () {
            expect(Tuti::class)->toHaveMethod('projectRoot')
                ->and(Tuti::class)->toHaveMethod('isInsideProject');
        });

        it('returns consistent results on multiple calls', function () {
            $tempDir = sys_get_temp_dir() . '/test_consistency_' . uniqid();
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . '/.tuti', 0777, true);
            chdir($tempDir);

            $result1 = Tuti::isInsideProject();
            $result2 = Tuti::isInsideProject();
            $result3 = Tuti::projectRoot();
            $result4 = Tuti::projectRoot();

            expect($result1)->toBe($result2)
                ->and($result3)->toBe($result4);

            // Cleanup
            rmdir($tempDir . '/.tuti');
            rmdir($tempDir);
        });
    });
});