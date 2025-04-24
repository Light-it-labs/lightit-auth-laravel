<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Lightit\Contracts\AuthInstallerInterface;

final class GoogleSSOInstaller implements AuthInstallerInterface
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
        $this->command->info('Installing Google api client...');

        if (! $this->composerInstaller->requirePackages(['google/apiclient'])) {
            $this->command->error('Failed to install google/apiclient');

            return;
        }

        $this->createAuthFiles();
        $this->copySharedFiles();

        $this->command->info('Client library for Google APIs installed successfully!');
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

    private function copySharedFiles(): void
    {
        $sharedStubPath = __DIR__ . '/../../Stubs/Exceptions/InvalidGoogleTokenException.stub';
        $sharedDestPath = base_path('src/Shared/App/Exceptions/Http/InvalidGoogleTokenException.php');

        $sharedDir = dirname($sharedDestPath);

        if (! is_dir($sharedDir)) {
            mkdir($sharedDir, 0755, true);
        }

        copy($sharedStubPath, $sharedDestPath);
    }
}
