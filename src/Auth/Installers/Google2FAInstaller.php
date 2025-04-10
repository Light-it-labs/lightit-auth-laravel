<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Lightit\Contracts\AuthInstallerInterface;

final class Google2FAInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

    public function install(): void
    {
        $this->command->info('Installing Google 2FA laravel and QR Code.');

        if (! $this->composerInstaller->requirePackages([
            'pragmarx/google2fa-laravel',
            'pragmarx/google2fa-qrcode',
            'bacon/bacon-qr-code',
        ])) {
            $this->command->error('Installing Google 2FA laravel and QR Code');

            return;
        }

        $this->createAuthFiles();
        $this->publishConfiguration();
        $this->copyMigration();
        $this->copyMiddlewares();
        $this->copyConfigFiles();
        $this->copyLangFiles();

        $this->command->info('Libraries for 2FA installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->command->info('Step 1/6: Creating authentication files...');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/Google2FA/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Actions/DisableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/DisableTwoFactorAuthenticationAction.php',
            '/Actions/EnableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/EnableTwoFactorAuthenticationAction.php',
            '/Actions/GenerateQRCodeAction.stub' => 'Domain/Actions/GenerateQRCodeAction.php',
            '/Actions/VerifyTwoFactorAuthenticationAction.stub' => 'Domain/Actions/VerifyTwoFactorAuthenticationAction.php',
            '/Actions/VerifyTwoFactorAuthenticationOTPAction.stub' => 'Domain/Actions/VerifyTwoFactorAuthenticationOTPAction.php',
            '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',

            '/Controllers/DisableTwoFactorAuthenticationController.stub' => 'App/Controllers/DisableTwoFactorAuthenticationController.php',
            '/Controllers/EnableTwoFactorAuthenticationController.stub' => 'App/Controllers/EnableTwoFactorAuthenticationController.php',
            '/Controllers/VerifyTwoFactorAuthenticationController.stub' => 'App/Controllers/VerifyTwoFactorAuthenticationController.php',

            '/Requests/TwoFactorAuthenticationCodeRequest.stub' => 'App/Requests/TwoFactorAuthenticationCodeRequest.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
        }
    }

    private function copyMigration(): void
    {
        $this->command->info('Step 3/6: Copying migration files...');

        $stub = __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php';

        copy(
            $stub,
            base_path($destination)
        );
    }

    private function copyMiddlewares(): void
    {
        $this->command->info('Step 4/6: Copying Middlewares classes...');

        $destinationFolder = 'src/Shared/App/Middlewares/';

        if (! is_dir($path = base_path($destinationFolder))) {
            mkdir($path, 0755, true);
        }

        $stubNames = [
            'ActiveTwoFactorAuthenticationMiddleware',
            'InactiveTwoFactorAuthenticationMiddleware',
        ];

        foreach ($stubNames as $stubName) {
            $stub = __DIR__ . "/../../Stubs/Google2FA/Auth/Middlewares/$stubName.stub";
            $destination = "{$destinationFolder}{$stubName}.php";

            copy(
                $stub,
                base_path($destination)
            );
        }
    }

    private function publishConfiguration(): void
    {
        $this->command->info('Step 2/6: Publishing configuration...');

        $this->command->call('vendor:publish', [
            '--provider' => 'PragmaRX\Google2FALaravel\ServiceProvider',
        ]);
    }

    private function copyConfigFiles(): void
    {
        $this->command->info('Step 5/6: Copying config files...');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__ . '/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );
    }

    private function copyLangFiles(): void
    {
        $this->command->info('Step 6/6: Copying lang files...');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        copy(
            __DIR__ . '/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );
    }
}
