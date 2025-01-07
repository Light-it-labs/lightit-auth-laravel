<?php

declare(strict_types=1);

namespace Lightit\Commands;

use Illuminate\Console\Command;
use Lightit\Enums\AuthDriver;

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
        foreach ($drivers as $driver) {
            match (AuthDriver::from($driver)) {
                AuthDriver::Jwt => $this->setupJWT(),
                AuthDriver::Sanctum => $this->setupSanctum(),
                AuthDriver::GoogleSso => $this->setupGoogleSSO(),
            };
        }
    }

    protected function setupJWT(): void
    {
        $this->info('Setting up JWT...');
    }

    protected function setupSanctum(): void
    {
        $this->info('Setting up Sanctum...');
    }

    protected function setupGoogleSSO(): void
    {
        $this->info('Setting up Google SSO...');
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
