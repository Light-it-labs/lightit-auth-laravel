<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Console\ConsoleProfile;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FARoutesCommand;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use Symfony\Component\Console\Output\BufferedOutput;

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

    it('fails the feature with an error, not a warning, when routes/api.php cannot be restored', function (): void {
        $buffer = new BufferedOutput();
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(null));
        $installer = new Google2FAInstaller(
            $setupOutput,
            new ComposerInstaller($setupOutput),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );
        chmod($this->root . '/routes/api.php', 0444);

        try {
            $succeeded = $setupOutput->feature('Two-Factor Authentication', static function () use ($installer): void {
                @(new ReflectionMethod($installer, 'registerRoutes'))->invoke($installer);
            });
        } finally {
            chmod($this->root . '/routes/api.php', 0644);
        }

        expect($succeeded)->toBeFalse()
            ->and($setupOutput->summary())->toBeFalse()
            ->and($buffer->fetch())
            ->toContain('✘ Two-Factor Authentication · failed')
            ->toContain(
                "    ✘ routes/api.php was left inconsistent while adding require __DIR__ . '/two-factor-auth.php'; "
                . '— inspect it'
            )
            ->not->toContain('    ! routes/api.php');
    })->skip(
        fn (): bool => ! function_exists('posix_getuid') || posix_getuid() === 0,
        'root bypasses file permissions'
    );
});
