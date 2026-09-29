<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Auth\Installers\SharedLoginFiles;
use Lightitlabs\Tests\Fixtures\FakeGoogleSSOInstallerFilesCommand;

/**
 * `GoogleLoginAction.stub` routes through `LoginByUserAction`, which depends
 * on `IssueTwoFactorChallengeAction` and the challenge action's own
 * dependencies (`TwoFactorReason`, `TwoFactorChallengeException`,
 * `TwoFactorAuthenticatable`). A Google-SSO-only install (no Google2FAInstaller
 * involved) must still write every one of `SharedLoginFiles::FILES`, or the
 * container can never resolve `LoginByUserAction` when `GoogleLoginAction` is
 * constructed, and the challenge action references classes PHPStan would
 * flag as missing in the consumer.
 *
 * Drives the file-writing steps directly through reflection, skipping
 * `requirePackages()`, which shells out to `composer require` and does not
 * belong in a unit test - mirrors the pattern Google2FAInstallerTest uses
 * for the same reason.
 */
describe('GoogleSSOInstaller writes the shared login primitives', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/google-sso-installer-' . bin2hex(random_bytes(6));
        mkdir($this->tempBase, 0755, true);
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempBase, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->tempBase);
    });

    it('writes every shared login file alongside the Google SSO stubs', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOInstallerFilesCommand());

        $this->artisan('google-sso-installer-files-fake')->assertSuccessful();

        foreach (SharedLoginFiles::FILES as $destination) {
            expect(file_exists($this->tempBase . '/src/Authentication/' . $destination))->toBeTrue();
        }
    });
});
