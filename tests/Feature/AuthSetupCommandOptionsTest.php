<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Prompt;

describe('AuthSetupCommand without the prompt', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/lightit-auth-setup-options-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->tempDir);
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempDir);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->tempDir);
    });

    it('installs the features passed with --feature without asking', function (): void {
        $this->artisan('auth:setup', ['--feature' => ['forgot-password'], '--no-interaction' => true])
            ->doesntExpectOutputToContain('Select features')
            ->expectsOutputToContain('✔ Forgot Password · 8 created')
            ->expectsOutputToContain('Authentication setup completed!')
            ->assertSuccessful();

        expect($this->tempDir . '/src/Authentication/App/Controllers/ForgotPasswordController.php')->toBeFile();
    });

    it('fails with the valid names when --feature is unknown', function (): void {
        $this->artisan('auth:setup', ['--feature' => ['passwordless'], '--no-interaction' => true])
            ->expectsOutputToContain(
                'Unknown --feature: passwordless. Pass --feature with one of: '
                . 'two-factor-authentication, roles-and-permissions, forgot-password.'
            )
            ->assertFailed();
    });

    it('installs nothing and succeeds, as before --feature existed, when it cannot ask', function (): void {
        $this->artisan('auth:setup', ['--no-interaction' => true])
            ->expectsOutputToContain(
                'No feature selected. Pass --feature with one of: '
                . 'two-factor-authentication, roles-and-permissions, forgot-password.'
            )
            ->doesntExpectOutputToContain('Summary')
            ->assertSuccessful();

        expect($this->tempDir . '/src')->not->toBeDirectory();
    });

    it('installs a feature passed twice with --feature only once', function (): void {
        $exitCode = Artisan::call('auth:setup', [
            '--feature' => ['forgot-password', 'forgot-password'],
            '--no-interaction' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(substr_count(Artisan::output(), '✔ Forgot Password'))->toBe(2);
    });

    it('restores the prompts theme it found when it finishes', function (): void {
        $before = Prompt::theme();

        $this->artisan('auth:setup', ['--feature' => ['forgot-password'], '--no-interaction' => true])
            ->assertSuccessful();

        expect(Prompt::theme())->toBe($before);
    });
});
