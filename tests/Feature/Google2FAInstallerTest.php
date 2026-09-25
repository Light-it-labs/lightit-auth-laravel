<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FAInstallerCommand;

describe('Google2FAInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir().'/google2fa-installer-'.bin2hex(random_bytes(6));
        mkdir($this->tempBase, 0755, true);
        mkdir($this->tempBase.'/database/migrations', 0755, true);
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->newFiles = [
            'src/Authentication/Domain/Actions/LoginByUserAction.php',
            'src/Authentication/Domain/Actions/TwoFactorLoginGate.php',
            'src/Authentication/Domain/Exceptions/TwoFactorChallengeException.php',
            'src/Authentication/Domain/TwoFactorAuthenticatable.php',
            'src/Authentication/Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            'src/Authentication/Domain/Actions/VerifyRecoveryCodeAction.php',
            'AUTH-2FA-TODO.md',
        ];
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

    it('writes the shared login primitives, the new Google2FA action stubs and the manual TODO doc', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')->assertSuccessful();

        foreach ($this->newFiles as $relative) {
            expect(file_exists($this->tempBase.'/'.$relative))->toBeTrue();
        }
    });

    it('prints the line to paste into LoginAction::execute(), inside a box, matching AUTH-2FA-TODO.md', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain('app(\Lightit\Authentication\Domain\Actions\TwoFactorLoginGate::class)->guardAgainstChallenge($user);')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase.'/AUTH-2FA-TODO.md'))
            ->toContain('app(\Lightit\Authentication\Domain\Actions\TwoFactorLoginGate::class)->guardAgainstChallenge($user);');
    });

    it('reports Skipped instead of recreating any file on a second run', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);
        $this->artisan('google2fa-installer-fake')->assertSuccessful();

        $filesAfterFirstRun = [];
        foreach ($this->newFiles as $relative) {
            $filesAfterFirstRun[$relative] = file_get_contents($this->tempBase.'/'.$relative);
        }

        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);
        $command = $this->artisan('google2fa-installer-fake');

        foreach ($this->newFiles as $relative) {
            $command->expectsOutputToContain('Skipped '.$relative);
        }

        $command->assertSuccessful();

        foreach ($filesAfterFirstRun as $relative => $contentsAfterFirstRun) {
            expect(file_get_contents($this->tempBase.'/'.$relative))->toBe($contentsAfterFirstRun);
        }
    });
});
