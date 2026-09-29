<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupTwoFactorCommand;

describe('AuthSetupCommand --frontend-path', function (): void {
    afterEach(function (): void {
        if (isset($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    });

    it('fails with a clear error before installing anything when --frontend-path is invalid', function (): void {
        $this->tempDir = sys_get_temp_dir().'/lightit-auth-setup-invalid-'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        $this->artisan('auth:setup', ['--frontend-path' => $this->tempDir])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();
    });

    it('fails for a --frontend-path that does not exist at all', function (): void {
        $missingPath = sys_get_temp_dir().'/lightit-auth-setup-missing-'.bin2hex(random_bytes(6));

        $this->artisan('auth:setup', ['--frontend-path' => $missingPath])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();
    });

    it('reaches the frontend installer with the parsed --frontend-path option', function (): void {
        $this->tempDir = sys_get_temp_dir().'/lightit-auth-setup-2fa-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->tempDir);

        Artisan::registerCommand(new FakeAuthSetupTwoFactorCommand);

        $this->artisan('auth-setup-two-factor-fake', ['--frontend-path' => $this->tempDir])
            ->expectsOutputToContain('Frontend project resolved:')
            ->assertSuccessful();

        expect(file_exists($this->tempDir.'/src/services/auth/two-factor/types.ts'))->toBeTrue();
    });

    it('resolves a relative --frontend-path against the Laravel application root, not the process cwd', function (): void {
        $relative = 'lightit-auth-setup-2fa-relative-'.bin2hex(random_bytes(6));
        $this->tempDir = base_path($relative);
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->tempDir);

        Artisan::registerCommand(new FakeAuthSetupTwoFactorCommand);

        $this->artisan('auth-setup-two-factor-fake', ['--frontend-path' => $relative])
            ->assertSuccessful();

        expect(file_exists($this->tempDir.'/src/services/auth/two-factor/types.ts'))->toBeTrue();
    });
});
