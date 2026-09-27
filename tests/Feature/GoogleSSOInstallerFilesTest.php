<?php

declare(strict_types=1);

use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\GoogleSSOInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * `GoogleLoginAction.stub` routes through `LoginByUserAction`, which depends
 * on `TwoFactorLoginGate`. A Google-SSO-only install (no Google2FAInstaller
 * involved) must still write both shared stubs, or the container can never
 * resolve `LoginByUserAction` when `GoogleLoginAction` is constructed.
 *
 * Drives the file-writing steps directly through reflection, skipping
 * `requirePackages()`, which shells out to `composer require` and does not
 * belong in a unit test - mirrors the pattern Google2FAInstallerTest uses
 * for the same reason.
 */
describe('GoogleSSOInstaller writes the shared login primitives', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir().'/google-sso-installer-'.bin2hex(random_bytes(6));
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

    it('writes LoginByUserAction and TwoFactorLoginGate alongside the Google SSO stubs', function (): void {
        $command = new class extends Illuminate\Console\Command
        {
            protected $signature = 'google-sso-installer-files-test';
        };

        $installer = new GoogleSSOInstaller(
            $command,
            new ComposerInstaller($command),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        foreach (['createAuthFiles', 'copySharedLoginFiles', 'copySharedFiles'] as $step) {
            $method = new ReflectionMethod($installer, $step);
            $method->setAccessible(true);
            $method->invoke($installer);
        }

        expect(file_exists($this->tempBase.'/src/Authentication/Domain/Actions/LoginByUserAction.php'))->toBeTrue();
        expect(file_exists($this->tempBase.'/src/Authentication/Domain/Actions/TwoFactorLoginGate.php'))->toBeTrue();
    });
});
