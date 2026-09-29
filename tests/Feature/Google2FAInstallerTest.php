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
            'src/Authentication/Domain/Actions/IssueTwoFactorChallengeAction.php',
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

    it('prints the constructor injection snippet for LoginAction, matching AUTH-2FA-TODO.md, without falling back to app()', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain('use Lightit\Authentication\Domain\Actions\IssueTwoFactorChallengeAction;')
            ->expectsOutputToContain('private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,')
            ->doesntExpectOutputToContain('app(')
            ->assertSuccessful();

        $todo = file_get_contents($this->tempBase.'/AUTH-2FA-TODO.md');

        expect($todo)
            ->toContain('use Lightit\Authentication\Domain\Actions\IssueTwoFactorChallengeAction;')
            ->toContain('private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,')
            ->not->toContain('app(');
    });

    it('prints the call to the injected challenge action, right before return $user;, matching AUTH-2FA-TODO.md', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain('$this->issueTwoFactorChallengeAction->execute($user);')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase.'/AUTH-2FA-TODO.md'))
            ->toContain('$this->issueTwoFactorChallengeAction->execute($user);');
    });

    it('documents that the 2fa rate limiter self-registers from routes/two-factor-auth.php, without a manual AppServiceProvider paste', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->doesntExpectOutputToContain('AppServiceProvider')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase.'/AUTH-2FA-TODO.md'))
            ->toContain('TwoFactorRateLimiter::register()')
            ->not->toContain('paste this line into AppServiceProvider');
    });

    it('prints and documents the second manual step: extending TwoFactorAuthenticatable', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain('Lightit\Users\Domain\Models\User must extend Lightit\Authentication\Domain\TwoFactorAuthenticatable')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase.'/AUTH-2FA-TODO.md'))
            ->toContain('\Lightit\Users\Domain\Models\User` must extend')
            ->toContain('\Lightit\Authentication\Domain\TwoFactorAuthenticatable`');
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

    it('writes the package config with mandatory and challenge_ttl_minutes, without ever calling vendor:publish', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->doesntExpectOutputToContain('Publishing configuration')
            ->assertSuccessful();

        // Asserted from the source text, not `require`d: the config
        // references `TwoFactorAuthenticatable` and PragmaRX's own
        // `Constants` class, neither of which this package's own test
        // process autoloads (both only exist in the consuming project once
        // the package and its stubs are installed there).
        $config = (string) file_get_contents($this->tempBase.'/config/google2fa.php');

        expect($config)
            ->toContain("'enabled' =>")
            ->and($config)->toContain("'mandatory' =>")
            ->and($config)->toContain("'challenge_ttl_minutes' =>");
    });

    it('never overwrites an already-published config/google2fa.php, even one shaped like a bare vendor publish', function (): void {
        mkdir($this->tempBase.'/config', 0755, true);
        file_put_contents(
            $this->tempBase.'/config/google2fa.php',
            "<?php\n\nreturn ['enabled' => true, 'lifetime' => 0, 'guard' => ''];\n",
        );

        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand);

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain('Skipped config/google2fa.php')
            ->assertSuccessful();

        $config = require $this->tempBase.'/config/google2fa.php';

        expect($config)->toBe(['enabled' => true, 'lifetime' => 0, 'guard' => '']);
    });
});
