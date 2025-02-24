<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Lightit\Contracts\AuthInstallerInterface;
use Lightit\Tools\FileManipulator;

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
            'bacon/bacon-qr-code'
        ])) {
            $this->command->error('Installing Google 2FA laravel and QR Code');

            return;
        }

//        $this->createAuthFiles();
        $this->publishConfiguration();
        $this->copyBaseClass();
        $this->copyMigration();
        $this->copyMiddlewares();

        $this->command->info('Libraries for 2FA installed successfully!');
    }


    private function createAuthFiles(): void
    {
        $this->command->info('Step 1/1: Creating authentication files...');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/GoogleSSO/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Requests/GoogleLoginRequest.stub' => 'App/Requests/GoogleLoginRequest.php',
            '/Controllers/GoogleLoginController.stub' => 'App/Controllers/GoogleLoginController.php',
            '/Actions/GoogleLoginAction.stub' => 'Domain/Actions/GoogleLoginAction.php',
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
        $this->command->info('Step 1/1: Copying migration files...');

        $stub = __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = "database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php";

        copy(
            $stub,
            base_path($destination)
        );
    }

    private function copyBaseClass(): void
    {
        $this->command->info('Step 1/1: Copying base class...');

        $destinationFolder = 'src/Authentication/Domain/';
        if (! is_dir($path = base_path($destinationFolder))) {
            mkdir($path, 0755, true);
        }

        $stub =  __DIR__ . '/../../Stubs/Google2FA/Auth/TwoFactorAuthenticatable.stub';
        $destination = $destinationFolder . 'TwoFactorAuthenticatable.php';
        copy(
            $stub,
            base_path($destination)
        );
    }

    private function copyMiddlewares(): void
    {
        $this->command->info('Step 1/1: Copying Middlewares classes...');

        $destinationFolder = 'src/Shared/App/Middlewares/';

        if (! is_dir($path = base_path($destinationFolder))) {
            mkdir($path, 0755, true);
        }

        $stubNames = [
            'ActiveTwoFactorAuthentication',
            'InactiveTwoFactorAuthentication'
        ];

        foreach ($stubNames as $stubName){
            $stub =  __DIR__ . "/../../Stubs/Google2FA/Auth/Middlewares/$stubName.stub";
            $destination = $destinationFolder . "$stubName.php";

            copy(
                $stub,
                base_path($destination)
            );
        }
    }

    private function publishConfiguration(): void
    {
        $this->command->info('Step 2/5: Publishing configuration...');

        $this->command->call('vendor:publish', [
            '--provider' => 'PragmaRX\Google2FALaravel\ServiceProvider',
        ]);

//        $this->copyConfigFiles();
    }

//    private function copyConfigFiles(): void
//    {
//        if (! is_dir(config_path())) {
//            mkdir(config_path(), 0755, true);
//        }
//
//        copy(
//            __DIR__ . '/../../Stubs/Google2FA/config/google2fa.stub',
//            config_path('google2fa.php')
//        );
//    }
}
