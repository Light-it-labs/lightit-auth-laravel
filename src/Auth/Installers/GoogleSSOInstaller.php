<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Contracts\SetupReporter;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;

final class GoogleSSOInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Enums',
        'Authentication/Domain/Exceptions',
    ];

    public function __construct(
        private readonly SetupReporter $reporter,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
    ) {
    }

    /**
     * @throws SetupAbortedException
     */
    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(['google/apiclient'])) {
            throw new SetupAbortedException('Failed to install google/apiclient');
        }

        $this->createAuthFiles();
        $this->copySharedLoginFiles();
        $this->copySharedFiles();
    }

    private function createAuthFiles(): void
    {
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
            $outcome = $this->stubCopier->copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->reportCopy($outcome, "src/Authentication/{$destination}");
        }
    }

    /**
     * `GoogleLoginAction` routes through `LoginByUserAction`, which depends
     * on `IssueTwoFactorChallengeAction` and the challenge action's own
     * dependencies. All of `SharedLoginFiles::FILES` are shared with
     * Google2FAInstaller so a Google-SSO-only install still resolves the
     * container binding.
     */
    private function copySharedLoginFiles(): void
    {
        foreach (SharedLoginFiles::FILES as $stub => $destination) {
            $outcome = $this->stubCopier->copy(
                SharedLoginFiles::stubsPath() . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->reportCopy($outcome, "src/Authentication/{$destination}");
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

        $outcome = $this->stubCopier->copy($sharedStubPath, $sharedDestPath);
        $this->reportCopy($outcome, 'src/Shared/App/Exceptions/Http/InvalidGoogleTokenException.php');
    }

    private function reportCopy(StubCopyOutcome $outcome, string $label): void
    {
        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($label),
            StubCopyOutcome::Skipped => $this->reporter->skipped($label),
        };
    }
}
