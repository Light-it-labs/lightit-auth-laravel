<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\AuthInstallerInterface;

final class ForgotPasswordInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    public function __construct(
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

    public function install(): void
    {
        $this->createAuthFiles();

        $this->composerInstaller->printSuccess('Forgot Password installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 1, 'Creating forgot password files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/ForgotPassword/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Controllers/ForgotPasswordController.stub'          => 'App/Controllers/ForgotPasswordController.php',
            '/Controllers/ResetPasswordController.stub'           => 'App/Controllers/ResetPasswordController.php',
            '/Requests/ForgotPasswordRequest.stub'                => 'App/Requests/ForgotPasswordRequest.php',
            '/Requests/ResetPasswordRequest.stub'                 => 'App/Requests/ResetPasswordRequest.php',
            '/Actions/SendPasswordResetLinkAction.stub'           => 'Domain/Actions/SendPasswordResetLinkAction.php',
            '/Actions/ResetPasswordAction.stub'                   => 'Domain/Actions/ResetPasswordAction.php',
            '/DataTransferObjects/ResetPasswordDto.stub'          => 'Domain/DataTransferObjects/ResetPasswordDto.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }
}
