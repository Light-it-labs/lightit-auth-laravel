<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Auth\Installers\SharedLoginFiles;
use Lightitlabs\Tests\Fixtures\FakeOtpInstallerCommand;

/**
 * `ConsumeOtpAction.stub` type-hints `LoginByUserAction`, which in turn
 * depends on `IssueTwoFactorChallengeAction` and the challenge action's own
 * dependencies (`TwoFactorReason`, `TwoFactorChallengeException`,
 * `TwoFactorAuthenticatable`). An OTP-only install (no Google2FAInstaller
 * involved) must still write every one of `SharedLoginFiles::FILES`, or the
 * container can never resolve `LoginByUserAction` when `ConsumeOtpAction` is
 * constructed, and the challenge action references classes PHPStan would
 * flag as missing in the consumer.
 */
describe('OtpInstaller writes the shared login primitives', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir().'/otp-installer-'.bin2hex(random_bytes(6));
        mkdir($this->tempBase, 0755, true);
        mkdir($this->tempBase.'/database/migrations', 0755, true);
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

    it('writes every shared login file alongside the OTP stubs', function (): void {
        Artisan::registerCommand(new FakeOtpInstallerCommand);

        $this->artisan('otp-installer-fake')->assertSuccessful();

        foreach (SharedLoginFiles::FILES as $destination) {
            expect(file_exists($this->tempBase.'/src/Authentication/'.$destination))->toBeTrue();
        }
    });
});
