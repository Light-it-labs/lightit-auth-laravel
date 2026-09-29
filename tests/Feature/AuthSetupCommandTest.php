<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupTwoFactorCommand;

describe('AuthSetupCommand --frontend-path', function (): void {
    it('fails with a clear error before installing anything when --frontend-path is invalid', function (): void {
        $invalidPath = sys_get_temp_dir().'/lightit-auth-setup-invalid-'.bin2hex(random_bytes(6));
        mkdir($invalidPath, 0755, true);

        $this->artisan('auth:setup', ['--frontend-path' => $invalidPath])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();

        File::deleteDirectory($invalidPath);
    });

    it('fails for a --frontend-path that does not exist at all', function (): void {
        $missingPath = sys_get_temp_dir().'/lightit-auth-setup-missing-'.bin2hex(random_bytes(6));

        $this->artisan('auth:setup', ['--frontend-path' => $missingPath])
            ->expectsOutputToContain('Invalid --frontend-path')
            ->assertFailed();
    });

    it('reaches the frontend installer with the parsed --frontend-path option', function (): void {
        $root = sys_get_temp_dir().'/lightit-auth-setup-2fa-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $root);

        Artisan::registerCommand(new FakeAuthSetupTwoFactorCommand);

        $this->artisan('auth-setup-two-factor-fake', ['--frontend-path' => $root])
            ->expectsOutputToContain('Frontend project resolved:')
            ->assertSuccessful();

        expect(file_exists($root.'/src/services/auth/two-factor/types.ts'))->toBeTrue();

        File::deleteDirectory($root);
    });
});
