<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * Every other Google2FAInstaller test drives one guarded method in isolation
 * because `install()` shells out to a real `composer require` - see
 * `Google2FAInstallerUserModelWarningTest`. That leaves `install()` itself,
 * the method that actually decides whether 2FA got wired in, completely
 * untested end to end: five real installer defects shipped past ~700
 * unit/fixture tests that mock every one of these boundaries.
 *
 * This suite runs `install()` for real: a throwaway scaffold, a real
 * `composer require` subprocess against local path repositories (no
 * network, no golden-fixture invention), and assertions on what actually
 * landed on disk and in the router - never a fragment match on generated
 * output (see the workspace rule against `toContain` on generated code).
 *
 * Coverage note (see the session report for the full breakdown): this
 * suite exercises the *backend* installer end to end, so it catches the
 * composer-exit-code defect this branch fixed and would catch a regression
 * in the login-gate wiring or backend route registration. It does not
 * exercise the frontend generator (Google2FAFrontendInstaller) against a
 * real react-template/TanStack Router build, so it would not have caught
 * the three frontend defects (unregistered Route export, unshipped UI
 * imports, nonexistent icon) on its own - those were caught by hand and are
 * already fixed on this branch by prior commits.
 */
describe('Google2FAInstaller end-to-end', function (): void {
    beforeEach(function (): void {
        $this->scaffold = sys_get_temp_dir().'/lightit-2fa-smoke-'.bin2hex(random_bytes(6));
        mkdir($this->scaffold, 0755, true);
        mkdir($this->scaffold.'/routes', 0755, true);
        file_put_contents(
            $this->scaffold.'/routes/api.php',
            "<?php\n\nuse Illuminate\Support\Facades\Route;\n"
        );
        mkdir($this->scaffold.'/database/migrations', 0755, true);

        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->scaffold);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->scaffold);
    });

    /**
     * Scaffolds a composer project that can genuinely `composer require` the
     * three 2FA packages without network access, via local `path`
     * repositories carrying nothing but a name and a version. packagist.org
     * is always explicitly disabled so a real network connection on the
     * machine running this test can never make a scenario pass or fail for
     * the wrong reason (e.g. actually reaching the real, public
     * pragmarx/google2fa-laravel package instead of exercising the fixture).
     *
     * @param  bool  $postInstallHookFails  Reproduces the bug this suite exists for:
     *                                      a composer plugin (e.g. CaptainHook trying to install git hooks with no
     *                                      `.git` directory present) failing in `post-update-cmd` - *after* every
     *                                      requested package has already been downloaded and added to `vendor/` and
     *                                      `composer.json` - so the overall `composer require` process exits non-zero
     *                                      despite the install having actually succeeded.
     * @param  bool  $withPackages  When false, no path repository is provided for
     *                              any package - with packagist.org disabled too, `composer require` then
     *                              fails deterministically and offline, simulating a real installation
     *                              failure (as opposed to an unrelated post-install hook failing).
     */
    function scaffoldComposerProject(string $root, bool $postInstallHookFails, bool $withPackages = true): void
    {
        $packages = [
            'pragmarx/google2fa-laravel' => 'pragmarx-google2fa-laravel',
            'pragmarx/google2fa-qrcode' => 'pragmarx-google2fa-qrcode',
            'bacon/bacon-qr-code' => 'bacon-bacon-qr-code',
        ];

        $repositories = [['packagist.org' => false]];

        if ($withPackages) {
            foreach ($packages as $name => $dir) {
                mkdir("{$root}/vendor-src/{$dir}", 0755, true);
                file_put_contents(
                    "{$root}/vendor-src/{$dir}/composer.json",
                    json_encode(['name' => $name, 'type' => 'library', 'version' => '0.0.1'])
                );
                $repositories[] = ['type' => 'path', 'url' => "vendor-src/{$dir}", 'options' => ['symlink' => false]];
            }
        }

        $composerJson = [
            'name' => 'lightit/smoke-scaffold',
            'type' => 'project',
            'repositories' => $repositories,
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ];

        if ($postInstallHookFails) {
            $composerJson['scripts'] = [
                'post-update-cmd' => 'php -r "fwrite(STDERR, \'simulated captainhook failure: no .git directory\'.PHP_EOL); exit(1);"',
            ];
        }

        file_put_contents("{$root}/composer.json", json_encode($composerJson));
    }

    function runGoogle2FAInstall(): void
    {
        Artisan::registerCommand(new class extends Command
        {
            protected $signature = 'google2fa-smoke-test';

            public function handle(): int
            {
                $composerInstaller = new ComposerInstaller($this);
                $stubCopier = new StubCopier(new OriginMarker('0.0.0-test'));
                $installer = new Google2FAInstaller($this, $composerInstaller, $stubCopier);

                $installer->install();

                return self::SUCCESS;
            }
        });

        $exitCode = Artisan::call('google2fa-smoke-test');

        if ($exitCode !== Command::SUCCESS) {
            throw new RuntimeException('google2fa-smoke-test command reported failure');
        }
    }

    it(
        'completes 2FA setup - domain files, login gate, and routes - when composer '
        .'require exits non-zero from an unrelated post-install hook, because the '
        .'packages actually landed',
        function (): void {
            scaffoldComposerProject($this->scaffold, postInstallHookFails: true);

            runGoogle2FAInstall();

            expect($this->scaffold.'/vendor/pragmarx/google2fa-laravel')->toBeDirectory()
                ->and($this->scaffold.'/src/Authentication/Domain/TwoFactorAuthenticatable.php')->toBeFile()
                ->and($this->scaffold.'/src/Authentication/Domain/Actions/LoginAction.php')->toBeFile();

            // Whole-file comparison, not a `toContain` fragment match: the login gate
            // must be *the* 2FA login pipeline stub, byte for byte - rendered fresh
            // from the same stub and marker this run used, not hand-duplicated here.
            $expected = sys_get_temp_dir().'/expected-login-action-'.bin2hex(random_bytes(4)).'.php';
            (new StubCopier(new OriginMarker('0.0.0-test')))->copy(
                __DIR__.'/../../../src/Stubs/Google2FA/Auth/Actions/LoginAction.stub',
                $expected
            );

            expect(file_get_contents($this->scaffold.'/src/Authentication/Domain/Actions/LoginAction.php'))
                ->toBe(file_get_contents($expected));
        }
    );

    it(
        'propagates a real composer failure as a command failure instead of reporting success',
        function (): void {
            // packagist.org disabled, no path repository provided: `composer require`
            // genuinely fails offline, and nothing lands - unlike the post-install-hook
            // scenario above, where the packages land despite the non-zero exit code.
            scaffoldComposerProject($this->scaffold, postInstallHookFails: false, withPackages: false);

            expect(fn () => runGoogle2FAInstall())->toThrow(RuntimeException::class);

            expect($this->scaffold.'/src/Authentication/Domain/TwoFactorAuthenticatable.php')
                ->not->toBeFile();
        }
    );

    it('registers the 2FA routes so they actually resolve at their real URL', function (): void {
        scaffoldComposerProject($this->scaffold, postInstallHookFails: false);

        runGoogle2FAInstall();

        // The scaffold has no composer PSR-4 map of its own (it only exists to
        // exercise `composer require`, not to be a real Laravel app), so single-action
        // controllers referenced by class-string need a real autoloader to be seen
        // as invokable - without one, route registration itself throws.
        spl_autoload_register(function (string $class) {
            if (! str_starts_with($class, 'Lightit\\')) {
                return;
            }

            $path = $this->scaffold.'/src/'.str_replace('\\', '/', substr($class, strlen('Lightit\\'))).'.php';

            if (is_file($path)) {
                require $path;
            }
        });

        // Load routes/api.php exactly the way the consuming application's routing
        // bootstrap would - which is what turns the appended `require` statement
        // into real, dispatchable `Route::` registrations, not just a file that
        // happens to parse.
        require $this->scaffold.'/routes/api.php';

        $route = Route::getRoutes()->match(Request::create('/2fa/setup', 'POST'));

        expect($route->uri())->toBe('2fa/setup');
    });
});
