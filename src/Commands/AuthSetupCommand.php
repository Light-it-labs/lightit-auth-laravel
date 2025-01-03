<?php

declare(strict_types=1);

namespace Lightit\Commands;

use Illuminate\Console\Command;
use Lightit\Enums\AuthDriver;

class AuthSetupCommand extends Command
{
    protected $signature = 'auth:setup';

    protected $description = 'Setup authentication structure based on the selected options (JWT, Sanctum, 2FA, etc.)';

    public function handle(): int
    {
        $this->info('Welcome to Auth Setup!');

        $selectedDrivers = $this->choice(
            'Select authentication drivers (comma-separated for multiple)',
            array_column(AuthDriver::cases(), 'value'),
            null,
            null,
            true
        );

        if (empty($selectedDrivers)) {
            $this->error('At least one authentication driver must be selected.');

            return self::FAILURE;
        }

        $enable2FA = $this->confirm('Would you like to enable Two-Factor Authentication?', false);
        $enableRolesAndPermissions = $this->confirm('Would you like to enable Roles and Permissions?', false);

        foreach ($selectedDrivers as $driver) {
            $this->setupAuthDriver(AuthDriver::from($driver));
        }

        if ($enable2FA) {
            $this->setup2FA();
        }

        if ($enableRolesAndPermissions) {
            $this->setupRolesAndPermissions();
        }

        $this->info('Authentication setup completed!');

        return self::SUCCESS;
    }

    protected function setupAuthDriver(AuthDriver $driver): void
    {
        match ($driver) {
            AuthDriver::JWT => $this->setupJWT(),
            AuthDriver::SANCTUM => $this->setupSanctum(),
            AuthDriver::GOOGLE_SSO => $this->setupGoogleSSO(),
        };
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
