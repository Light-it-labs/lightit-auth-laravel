<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubCopier;

final class SanctumInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Resources',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
    ) {
    }

    public function install(): void
    {
        $this->command->call('install:api');

        $this->createAuthFiles();

        $this->composerInstaller->printSuccess('Sanctum-API Token Authentication installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 1, 'Creating authentication files');

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
        $sharedStubsPath = __DIR__ . '/../../Stubs/Shared/Auth';

        $sharedFiles = [
            '/DataTransferObjects/CredentialsDto.stub' => 'Domain/DataTransferObjects/CredentialsDto.php',
            '/Requests/LoginRequest.stub' => 'App/Requests/LoginRequest.php',
        ];

        $driverFiles = [
            '/Resources/LoginResource.stub' => 'App/Resources/LoginResource.php',
            '/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
            '/Controllers/LoginController.stub' => 'App/Controllers/LoginController.php',
            '/Controllers/LogoutController.stub' => 'App/Controllers/LogoutController.php',
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
            '/DataTransferObjects/LoginDto.stub' => 'Domain/DataTransferObjects/LoginDto.php',
        ];

        foreach ($sharedFiles as $stub => $destination) {
            $this->stubCopier->copy(
                $sharedStubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }

        foreach ($driverFiles as $stub => $destination) {
            $this->stubCopier->copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }
}
