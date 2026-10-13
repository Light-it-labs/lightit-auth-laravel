<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Contracts\SetupReporter;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;

final class OtpInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Notifications',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Models',
        'Authentication/Domain/Enums',
        'Authentication/Domain/Exceptions',
    ];

    public function __construct(
        private readonly SetupReporter $reporter,
        private readonly StubCopier $stubCopier,
    ) {
    }

    public function install(): void
    {
        $this->createAuthFiles();
        $this->copySharedFiles();
        $this->copyMigration();
        $this->copyConfigFile();
    }

    private function createAuthFiles(): void
    {
        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/Otp/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    /**
     * `ConsumeOtpAction` type-hints `LoginByUserAction`, which in turn
     * depends on `IssueTwoFactorChallengeAction` and the challenge action's
     * own dependencies. All of `SharedLoginFiles::FILES` are shared with
     * Google2FAInstaller so an OTP-only install still resolves the
     * container binding.
     */
    private function copySharedFiles(): void
    {
        foreach (SharedLoginFiles::FILES as $stub => $destination) {
            $outcome = $this->stubCopier->copy(
                SharedLoginFiles::stubsPath() . $stub,
                base_path("src/Authentication/{$destination}")
            );

            match ($outcome) {
                StubCopyOutcome::Written => $this->reporter->written("src/Authentication/{$destination}"),
                StubCopyOutcome::Skipped => $this->reporter->skipped("src/Authentication/{$destination}"),
            };
        }
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Controllers/OtpSendController.stub' => 'App/Controllers/OtpSendController.php',
            '/Controllers/OtpVerifyController.stub' => 'App/Controllers/OtpVerifyController.php',
            '/Requests/OtpSendRequest.stub' => 'App/Requests/OtpSendRequest.php',
            '/Requests/OtpVerifyRequest.stub' => 'App/Requests/OtpVerifyRequest.php',
            '/Notifications/OtpNotification.stub' => 'App/Notifications/OtpNotification.php',
            '/Actions/SendOtpAction.stub' => 'Domain/Actions/SendOtpAction.php',
            '/Actions/ConsumeOtpAction.stub' => 'Domain/Actions/ConsumeOtpAction.php',
            '/DataTransferObjects/OtpSendDto.stub' => 'Domain/DataTransferObjects/OtpSendDto.php',
            '/DataTransferObjects/OtpVerifyDto.stub' => 'Domain/DataTransferObjects/OtpVerifyDto.php',
            '/Models/Otp.stub' => 'Domain/Models/Otp.php',
            '/Exceptions/OtpException.stub' => 'Domain/Exceptions/OtpException.php',
        ];

        foreach ($files as $stub => $destination) {
            $outcome = $this->stubCopier->copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );

            match ($outcome) {
                StubCopyOutcome::Written => $this->reporter->written("src/Authentication/{$destination}"),
                StubCopyOutcome::Skipped => $this->reporter->skipped("src/Authentication/{$destination}"),
            };
        }
    }

    private function copyMigration(): void
    {
        $migrationName = 'create_otps_table';
        $migrationsDirectory = base_path('database/migrations');

        if ($this->migrationAlreadyExists($migrationsDirectory, $migrationName)) {
            $this->reporter->skipped("database/migrations/*_{$migrationName}.php");

            return;
        }

        $stub = __DIR__ . '/../../Stubs/Otp/database/migrations/create_otps_table.stub';
        $timestamp = date('Y_m_d_His');
        $destination = "database/migrations/{$timestamp}_{$migrationName}.php";

        $outcome = $this->stubCopier->copy(
            $stub,
            base_path($destination)
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($destination),
            StubCopyOutcome::Skipped => $this->reporter->skipped($destination),
        };
    }

    /**
     * The migration file is timestamped at copy time, so the destination path is never stable
     * enough for `StubCopier`'s own no-overwrite guard to catch a re-run. Glob for any existing
     * migration ending in the same name instead of trusting the exact filename.
     */
    private function migrationAlreadyExists(string $migrationsDirectory, string $migrationName): bool
    {
        $matches = glob("{$migrationsDirectory}/*_{$migrationName}.php");

        return $matches !== false && $matches !== [];
    }

    private function copyConfigFile(): void
    {
        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $outcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Otp/config/otp.stub',
            config_path('otp.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written('config/otp.php'),
            StubCopyOutcome::Skipped => $this->reporter->skipped('config/otp.php'),
        };
    }
}
