<?php

declare(strict_types=1);

namespace Lightitlabs;

use Lightitlabs\Commands\AuthSetupCommand;
use Lightitlabs\Commands\LightitCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LightitServiceProvider extends PackageServiceProvider
{
    /**
     * FQCN of the stub the consuming app gets at
     * `Lightit\Authentication\Domain\TwoFactorRateLimiter` once it installs 2FA.
     * Referenced as a string, not a class-constant import, because this
     * package never ships that class itself - it only writes it into the
     * consumer.
     */
    private const TWO_FACTOR_RATE_LIMITER = 'Lightit\Authentication\Domain\TwoFactorRateLimiter';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('lightit-auth-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_lightit_auth_laravel_table')
            ->hasCommand(LightitCommand::class)
            ->hasCommand(AuthSetupCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->publishes([
            __DIR__ . '/Models' => app_path('Models'),
        ], 'lightit-auth-models');
    }

    public function packageBooted(): void
    {
        if (class_exists(self::TWO_FACTOR_RATE_LIMITER)) {
            self::TWO_FACTOR_RATE_LIMITER::register();
        }
    }
}
