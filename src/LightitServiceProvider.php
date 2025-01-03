<?php

namespace Lightit\Lightit;

use Lightit\Lightit\Commands\AuthSetupCommand;
use Lightit\Lightit\Commands\LightitCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LightitServiceProvider extends PackageServiceProvider
{
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
            ->hasCommand(LightitCommand::class);

        $package
            ->name('lightit-auth-laravel')
            ->hasCommand(AuthSetupCommand::class);
    }
}
