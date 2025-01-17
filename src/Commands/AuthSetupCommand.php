<?php

declare(strict_types=1);

namespace Lightit\Commands;

use Illuminate\Console\Command;
use Lightit\Auth\Installers\ComposerInstaller;
use Lightit\Auth\Installers\JWTInstaller;
use Lightit\Enums\AuthDriver;
use Lightit\Tools\FileManipulator;

class AuthSetupCommand extends Command
{
    protected $signature = 'auth:setup';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
    {
        $drivers = $this->choice(
            'Select authentication drivers (comma-separated for multiple)',
            array_column(AuthDriver::cases(), 'value'),
            null,
            null,
            true
        );

        $enable2FA = $this->confirm('Would you like to enable Two-Factor Authentication?', false);
        $enableRolesAndPermissions = $this->confirm('Would you like to enable Roles and Permissions?', false);

        /** @var array<string> $drivers */
        $this->setupDrivers($drivers);

        if ($enable2FA) {
            $this->setup2FA();
        }

        if ($enableRolesAndPermissions) {
            $this->setupRolesAndPermissions();
        }

        $this->info('Authentication setup completed!');

        return self::SUCCESS;
    }

    /**
     * @param array<string> $drivers
     */
    protected function setupDrivers(array $drivers): void
    {
        $setup = [
            AuthDriver::Jwt->value => fn () => $this->setupJWT(),
            AuthDriver::Sanctum->value => fn () => $this->setupSanctum(),
            AuthDriver::GoogleSso->value => fn () => $this->setupGoogleSSO(),
        ];

        foreach ($drivers as $driver) {
            $key = AuthDriver::from($driver)->value;
            $setup[$key]();
        }
    }

    protected function setupJWT(): void
    {
        $this->info('Setting up JWT...');

        $composerInstaller = new ComposerInstaller($this);
        $fileManipulator = new FileManipulator($this);
        $jwtInstaller = new JWTInstaller($this, $composerInstaller, $fileManipulator);
        $jwtInstaller->install();
    }

    protected function setupSanctum(): void
    {
        $this->info('Setting up Sanctum...');
    }

    protected function setupGoogleSSO(): void
    {
        $this->info('Setting up Google SSO...');

        $composerInstaller = new ComposerInstaller($this);
        $fileManipulator = new FileManipulator($this);
        $jwtInstaller = new JWTInstaller($this, $composerInstaller, $fileManipulator);
        $jwtInstaller->install();
    }

    protected function setup2FA(): void
    {
        $this->info('Setting up 2FA...');
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->info('Setting up Roles and Permissions...');
    }
}
