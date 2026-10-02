<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupTwoFactorCommand;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupWithoutComposerCommand;

describe('AuthSetupCommand --frontend-path', function (): void {
    afterEach(function (): void {
        if (isset($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    });

    it('fails with a clear error before installing anything when --frontend-path is invalid', function (): void {
        $this->tempDir = sys_get_temp_dir() . '/lightit-auth-setup-invalid-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        $this->artisan('auth:setup', ['--frontend-path' => $this->tempDir])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();
    });

    it('fails for a --frontend-path that does not exist at all', function (): void {
        $missingPath = sys_get_temp_dir() . '/lightit-auth-setup-missing-' . bin2hex(random_bytes(6));

        $this->artisan('auth:setup', ['--frontend-path' => $missingPath])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();
    });

    it('reaches the frontend installer with the parsed --frontend-path option', function (): void {
        $this->tempDir = sys_get_temp_dir() . '/lightit-auth-setup-2fa-' . bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__ . '/../Fixtures/frontend/react-project', $this->tempDir);

        Artisan::registerCommand(new FakeAuthSetupTwoFactorCommand());

        $this->artisan('auth-setup-two-factor-fake', ['--frontend-path' => $this->tempDir])
            ->expectsOutputToContain('Frontend project resolved:')
            ->assertSuccessful();

        expect(file_exists($this->tempDir . '/src/services/auth/two-factor/types.ts'))->toBeTrue();
    });

    it(
        'resolves a relative --frontend-path against the Laravel application root, not the process cwd',
        function (): void {
            $relative = 'lightit-auth-setup-2fa-relative-' . bin2hex(random_bytes(6));
            $this->tempDir = base_path($relative);
            File::copyDirectory(__DIR__ . '/../Fixtures/frontend/react-project', $this->tempDir);
    
            Artisan::registerCommand(new FakeAuthSetupTwoFactorCommand());
    
            $this->artisan('auth-setup-two-factor-fake', ['--frontend-path' => $relative])
                ->assertSuccessful();
    
            expect(file_exists($this->tempDir . '/src/services/auth/two-factor/types.ts'))->toBeTrue();
        }
    );
});

describe('AuthSetupCommand when a feature fails', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/lightit-auth-setup-failure-' . bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__ . '/../Fixtures/frontend/react-project', $this->tempDir . '/frontend');
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempDir);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->tempDir);
    });

    it(
        'reports the 2FA composer failure, skips the 2FA frontend, still installs the rest and exits non-zero',
        function (): void {
            file_put_contents($this->tempDir . '/composer.json', '{ not json');
    
            $this->artisan('auth:setup', ['--frontend-path' => $this->tempDir . '/frontend'])
                ->expectsQuestion('Select features', ['two-factor-authentication', 'forgot-password'])
                ->expectsOutputToContain(
                    'Two-Factor Authentication setup failed: Failed to install pragmarx/google2fa-laravel, '
                    . 'pragmarx/google2fa-qrcode, bacon/bacon-qr-code'
                )
                ->doesntExpectOutputToContain('Setting up 2FA frontend')
                ->expectsOutputToContain('Setting up Forgot Password')
                ->expectsOutputToContain('Authentication setup did not complete: Two-Factor Authentication failed.')
                ->expectsOutputToContain('It is safe to re-run')
                ->doesntExpectOutputToContain('Authentication setup completed!')
                ->assertFailed();
    
            expect($this->tempDir . '/src/Authentication/App/Controllers/ForgotPasswordController.php')->toBeFile()
                ->and($this->tempDir . '/src/Authentication/Domain/TwoFactorAuthenticatable.php')->not->toBeFile()
                ->and($this->tempDir . '/frontend/src/services/auth/two-factor')->not->toBeDirectory();
        }
    );

    it(
        'reports a failure mid-way through an installer, keeps what it wrote, and a re-run finishes it',
        function (): void {
            $blockedDirectory = $this->tempDir . '/frontend/src/routes/(public)/_guest/two-factor';
            File::ensureDirectoryExists(\dirname($blockedDirectory));
            file_put_contents($blockedDirectory, 'a file where the screens directory goes');
    
            Artisan::registerCommand(new FakeAuthSetupWithoutComposerCommand());
    
            $this->artisan('auth-setup-without-composer-fake', ['--frontend-path' => $this->tempDir . '/frontend'])
                ->expectsQuestion('Select features', ['two-factor-authentication', 'forgot-password'])
                ->expectsOutputToContain('Two-Factor Authentication setup failed:')
                ->expectsOutputToContain('The files it wrote before failing were kept.')
                ->expectsOutputToContain('Setting up Forgot Password')
                ->expectsOutputToContain('Authentication setup did not complete: Two-Factor Authentication failed.')
                ->assertFailed();
    
            expect($this->tempDir . '/frontend/src/services/auth/two-factor/api.ts')->toBeFile()
                ->and($this->tempDir . '/src/Authentication/App/Controllers/ForgotPasswordController.php')->toBeFile();
    
            unlink($blockedDirectory);
    
            $this->artisan('auth-setup-without-composer-fake', ['--frontend-path' => $this->tempDir . '/frontend'])
                ->expectsQuestion('Select features', ['two-factor-authentication', 'forgot-password'])
                ->expectsOutputToContain('Skipped src/services/auth/two-factor/api.ts')
                ->expectsOutputToContain('Authentication setup completed!')
                ->assertSuccessful();
    
            expect($this->tempDir . '/frontend/src/routes/(public)/_guest/two-factor/page.tsx')->toBeFile();
        }
    );
});
