<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Tests\Fixtures\FakeOtpInstallerCommand;

/**
 * `ConsumeOtpAction.stub` type-hints `LoginByUserAction`, which in turn
 * depends on `IssueTwoFactorChallengeAction`. An OTP-only install (no Google2FAInstaller
 * involved) must still write both shared stubs, or the container can never
 * resolve `LoginByUserAction` when `ConsumeOtpAction` is constructed.
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

    it('writes LoginByUserAction and IssueTwoFactorChallengeAction alongside the OTP stubs', function (): void {
        Artisan::registerCommand(new FakeOtpInstallerCommand);

        $this->artisan('otp-installer-fake')->assertSuccessful();

        expect(file_exists($this->tempBase.'/src/Authentication/Domain/Actions/LoginByUserAction.php'))->toBeTrue();
        expect(file_exists($this->tempBase.'/src/Authentication/Domain/Actions/IssueTwoFactorChallengeAction.php'))->toBeTrue();
    });
});
