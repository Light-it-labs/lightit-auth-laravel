<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\AuthInstallerInterface;

final class OtpInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Notifications',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Models',
        'Authentication/Domain/Exceptions',
    ];

    public function __construct(
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

    public function install(): void
    {
        $this->createAuthFiles();
        $this->copyMigration();
        $this->copyConfigFile();

        $this->composerInstaller->printSuccess('OTP installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 3, 'Creating OTP files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/Otp/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Controllers/OtpSendController.stub'                       => 'App/Controllers/OtpSendController.php',
            '/Controllers/OtpVerifyController.stub'                     => 'App/Controllers/OtpVerifyController.php',
            '/Requests/OtpSendRequest.stub'                             => 'App/Requests/OtpSendRequest.php',
            '/Requests/OtpVerifyRequest.stub'                           => 'App/Requests/OtpVerifyRequest.php',
            '/Notifications/OtpNotification.stub'                       => 'App/Notifications/OtpNotification.php',
            '/Actions/SendOtpAction.stub'                               => 'Domain/Actions/SendOtpAction.php',
            '/Actions/ConsumeOtpAction.stub'                            => 'Domain/Actions/ConsumeOtpAction.php',
            '/DataTransferObjects/OtpVerifyDto.stub'                    => 'Domain/DataTransferObjects/OtpVerifyDto.php',
            '/Models/Otp.stub'                                          => 'Domain/Models/Otp.php',
            '/Exceptions/InvalidOtpException.stub'                      => 'Domain/Exceptions/InvalidOtpException.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(2, 3, 'Copying migration files');

        $stub = __DIR__ . '/../../Stubs/Otp/database/migrations/create_otps_table.stub';
        $timestamp = date('Y_m_d_His');
        $destination = "database/migrations/{$timestamp}_create_otps_table.php";

        copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(3, 3, 'Copying config file');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__ . '/../../Stubs/Otp/config/otp.stub',
            config_path('otp.php')
        );
        $this->composerInstaller->printConfigPublished('Config file published: config/otp.php');
    }
}
