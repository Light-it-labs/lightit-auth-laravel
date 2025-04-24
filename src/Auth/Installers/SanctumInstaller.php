<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Lightit\Contracts\AuthInstallerInterface;

final class SanctumInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    public function __construct(
        private readonly Command $command,
    ) {
    }

    public function install(): void
    {
        $this->command->info('Installing Sanctum-API Token Authentication...');

        $this->createAuthFiles();

        $this->command->info('Sanctum-API Token Authentication installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->command->info('Step 1/1: Creating authentication files...');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/Sanctum/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Requests/LoginRequest.stub' => 'App/Requests/LoginRequest.php',
            '/Controllers/LoginController.stub' => 'App/Controllers/LoginController.php',
            '/Controllers/LogoutController.stub' => 'App/Controllers/LogoutController.php',
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
            '/DataTransferObjects/LoginDto.stub' => 'Domain/DataTransferObjects/LoginDto.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
        }
    }
}
