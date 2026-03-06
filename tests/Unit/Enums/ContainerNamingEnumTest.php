<?php

declare(strict_types=1);

use App\Enums\ContainerNamingEnum;

describe('ContainerNamingEnum', function (): void {
    describe('Network', function (): void {
        it('returns correct network key', function (): void {
            expect(ContainerNamingEnum::Network->key('myapp'))
                ->toBe('myapp_network');
        });

        it('returns correct network name with default env', function (): void {
            expect(ContainerNamingEnum::Network->name('myapp'))
                ->toBe('myapp_dev_network');
        });

        it('returns correct network name with custom env', function (): void {
            expect(ContainerNamingEnum::Network->name('myapp', env: 'prod'))
                ->toBe('myapp_prod_network');
        });

        it('returns correct network name with staging env', function (): void {
            expect(ContainerNamingEnum::Network->name('myapp', env: 'staging'))
                ->toBe('myapp_staging_network');
        });
    });

    describe('Container', function (): void {
        it('returns correct container name', function (): void {
            expect(ContainerNamingEnum::Container->name('myapp', 'database'))
                ->toBe('myapp_dev_database');
        });

        it('returns correct container name with custom env', function (): void {
            expect(ContainerNamingEnum::Container->name('myapp', 'app', 'staging'))
                ->toBe('myapp_staging_app');
        });

        it('returns correct container name for app service', function (): void {
            expect(ContainerNamingEnum::Container->name('test-project', 'app'))
                ->toBe('test-project_dev_app');
        });
    });

    describe('Volume', function (): void {
        it('returns correct volume name', function (): void {
            expect(ContainerNamingEnum::Volume->name('myapp', 'postgres_data'))
                ->toBe('myapp_dev_postgres_data');
        });

        it('returns correct volume name with custom env', function (): void {
            expect(ContainerNamingEnum::Volume->name('myapp', 'redis_data', 'prod'))
                ->toBe('myapp_prod_redis_data');
        });

        it('returns correct volume name with env var syntax', function (): void {
            expect(ContainerNamingEnum::Volume->withEnvVar('myapp', 'postgres_data'))
                ->toBe('myapp_${APP_ENV:-dev}_postgres_data');
        });

        it('returns correct volume key without env', function (): void {
            expect(ContainerNamingEnum::Volume->volumeKey('myapp', 'postgres_data'))
                ->toBe('myapp_postgres_data');
        });

        it('handles project names with hyphens', function (): void {
            expect(ContainerNamingEnum::Volume->volumeKey('my-project', 'redis_data'))
                ->toBe('my-project_redis_data');
        });
    });

    describe('Constants', function (): void {
        it('has default network constant', function (): void {
            expect(ContainerNamingEnum::DEFAULT_NETWORK)->toBe('app_network');
        });

        it('has default env constant', function (): void {
            expect(ContainerNamingEnum::DEFAULT_ENV)->toBe('dev');
        });
    });

    describe('Edge cases', function (): void {
        it('throws for key() on Container', function (): void {
            expect(fn (): string => ContainerNamingEnum::Container->key('myapp'))
                ->toThrow(LogicException::class, 'key() only available for Network');
        });

        it('throws for key() on Volume', function (): void {
            expect(fn (): string => ContainerNamingEnum::Volume->key('myapp'))
                ->toThrow(LogicException::class, 'key() only available for Network');
        });

        it('throws for withEnvVar() on Network', function (): void {
            expect(fn (): string => ContainerNamingEnum::Network->withEnvVar('myapp', 'data'))
                ->toThrow(LogicException::class, 'withEnvVar() only available for Volume');
        });

        it('throws for withEnvVar() on Container', function (): void {
            expect(fn (): string => ContainerNamingEnum::Container->withEnvVar('myapp', 'data'))
                ->toThrow(LogicException::class, 'withEnvVar() only available for Volume');
        });

        it('throws for volumeKey() on Network', function (): void {
            expect(fn (): string => ContainerNamingEnum::Network->volumeKey('myapp', 'data'))
                ->toThrow(LogicException::class, 'volumeKey() only available for Volume');
        });

        it('throws for volumeKey() on Container', function (): void {
            expect(fn (): string => ContainerNamingEnum::Container->volumeKey('myapp', 'data'))
                ->toThrow(LogicException::class, 'volumeKey() only available for Volume');
        });
    });

    describe('Enum cases', function (): void {
        it('has Container case', function (): void {
            expect(ContainerNamingEnum::Container->value)->toBe('container');
        });

        it('has Network case', function (): void {
            expect(ContainerNamingEnum::Network->value)->toBe('network');
        });

        it('has Volume case', function (): void {
            expect(ContainerNamingEnum::Volume->value)->toBe('volume');
        });
    });
});
