<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FARoutesCommand;

describe('Google2FAInstaller route registration', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/lightit-2fa-routes-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/routes', 0755, true);
        file_put_contents($this->root . '/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");

        $this->app->setBasePath($this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('always targets base_path(routes/api.php), regardless of the run count', function (): void {
        Artisan::registerCommand(new FakeGoogle2FARoutesCommand());
        $this->artisan('google2fa-routes-fake')->assertSuccessful();

        Artisan::registerCommand(new FakeGoogle2FARoutesCommand());
        $this->artisan('google2fa-routes-fake')->assertSuccessful();

        expect($this->root . '/routes/two-factor-auth.php')->toBeFile();

        $apiRoutes = (string) file_get_contents($this->root . '/routes/api.php');
        expect(substr_count($apiRoutes, "require __DIR__ . '/two-factor-auth.php';"))->toBe(1);
    });

    it('aborts the setup when routes/api.php is left in an inconsistent state', function (): void {
        chmod($this->root . '/routes/api.php', 0444);
        set_error_handler(static fn (): bool => true);
        Artisan::registerCommand(new FakeGoogle2FARoutesCommand());

        try {
            expect(fn () => $this->artisan('google2fa-routes-fake')->run())
                ->toThrow(SetupAbortedException::class, 'routes/api.php was left in an inconsistent state');
        } finally {
            restore_error_handler();
            chmod($this->root . '/routes/api.php', 0644);
        }
    })->skip(
        fn (): bool => ! function_exists('posix_getuid') || posix_getuid() === 0,
        'root bypasses file permissions'
    );
});
