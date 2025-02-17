<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

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

        $stub = '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/add_two_factor_authentication_columns.php';

        copy(
            $stub,
            base_path($destination)
        );
    }

    private function copyBaseClass(): void
    {
        $this->command->info('Step 1/1: Copying base class...');

        $stub = '/../../Stubs/Google2FA/Auth/TwoFactorAuthenticatable.stub';
        $destination = 'Domain/TwoFactorAuthenticatable.php';

        copy(
            $stub,
            base_path($destination)
        );
    }

    private function copyMiddlewares(): void
    {
//        $stub = '/../../../database/migrations/add_two_factor_authentication_columns.stub';
//        $destination = 'database/migrations/add_two_factor_authentication_columns.php';
//
//        copy(
//            $stub,
//            base_path($destination)
//        );
    }
}
