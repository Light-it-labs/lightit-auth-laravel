<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\SharedLoginFiles;
use Lightitlabs\Auth\Installers\SocialLoginInstaller;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

describe('SocialLoginInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/social-login-installer-' . bin2hex(random_bytes(6));
        mkdir($this->tempBase . '/routes', 0755, true);
        file_put_contents($this->tempBase . '/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->writtenFiles = [
            ...array_map(
                static fn (string $destination): string => 'src/Authentication/' . $destination,
                [...array_values(SocialLoginInstaller::FILES), ...array_values(SharedLoginFiles::FILES)],
            ),
            'database/migrations/2026_10_05_000000_create_social_accounts_table.php',
            'config/social-login.php',
            'routes/social-login.php',
            'AUTH-SOCIAL-TODO.md',
        ];

        Artisan::command('social-login-installer-fake {--with-composer}', function (): void {
            $installer = new SocialLoginInstaller(
                $this,
                new ComposerInstaller($this),
                new StubCopier(new OriginMarker('0.0.0-test')),
            );

            $this->option('with-composer') === true ? $installer->install() : $installer->writeFiles();
        });
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->tempBase);
    });

    it(
        'writes the social login stubs, the shared login files, the migration, config, routes and TODO',
        function (): void {
            $this->artisan('social-login-installer-fake')->assertSuccessful();

            foreach ($this->writtenFiles as $relative) {
                expect($this->tempBase . '/' . $relative)->toBeFile();
                expect(file_get_contents($this->tempBase . '/' . $relative))->not->toContain('{{');
            }
        }
    );

    it('writes LoginByUserAction and its 2FA gate even when 2FA is not installed', function (): void {
        $this->artisan('social-login-installer-fake')->assertSuccessful();

        expect($this->tempBase . '/src/Authentication/Domain/Actions/LoginByUserAction.php')->toBeFile()
            ->and($this->tempBase . '/src/Authentication/Domain/Actions/IssueTwoFactorChallengeAction.php')->toBeFile()
            ->and($this->tempBase . '/src/Authentication/Domain/Actions/SocialLoginAction.php')->toBeFile();
    });

    it(
        'reports Skipped and leaves every file untouched on a second run, the app\'s own config included',
        function (): void {
            mkdir($this->tempBase . '/config');
            file_put_contents($this->tempBase . '/config/social-login.php', "<?php\n\nreturn ['custom' => true];\n");

            $this->artisan('social-login-installer-fake')
                ->expectsOutputToContain('Skipped config/social-login.php')
                ->assertSuccessful();

            expect(file_get_contents($this->tempBase . '/config/social-login.php'))
                ->toBe("<?php\n\nreturn ['custom' => true];\n");

            $before = [];
            foreach ($this->writtenFiles as $relative) {
                $before[$relative] = file_get_contents($this->tempBase . '/' . $relative);
            }

            $command = $this->artisan('social-login-installer-fake');
            foreach ($this->writtenFiles as $relative) {
                $command->expectsOutputToContain('Skipped ' . $relative);
            }
            $command->assertSuccessful();

            foreach ($before as $relative => $contents) {
                expect(file_get_contents($this->tempBase . '/' . $relative))->toBe($contents);
            }
        }
    );

    it('requires routes/social-login.php from routes/api.php exactly once', function (): void {
        $this->artisan('social-login-installer-fake')
            ->expectsOutputToContain("Updated routes/api.php: require __DIR__ . '/social-login.php';")
            ->assertSuccessful();
        $this->artisan('social-login-installer-fake')
            ->expectsOutputToContain('Social login routes already required in routes/api.php')
            ->assertSuccessful();

        expect(substr_count(
            (string) file_get_contents($this->tempBase . '/routes/api.php'),
            "require __DIR__ . '/social-login.php';"
        ))->toBe(1);
    });

    it('aborts before writing anything when composer cannot require php-jwt', function (): void {
        file_put_contents($this->tempBase . '/composer.json', '{ not json');

        expect(fn () => $this->artisan('social-login-installer-fake', ['--with-composer' => true])->run())
            ->toThrow(SetupAbortedException::class, 'Failed to install firebase/php-jwt');

        expect($this->tempBase . '/src')->not->toBeDirectory()
            ->and($this->tempBase . '/AUTH-SOCIAL-TODO.md')->not->toBeFile();
    });

    it('registers Google in the provider config, reading the client id from GOOGLE_CLIENT_ID', function (): void {
        $this->artisan('social-login-installer-fake')->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/config/social-login.php'))
            ->toContain('use Lightit\Authentication\Domain\SocialProviders\GoogleProvider;')
            ->toContain('GoogleProvider::NAME => GoogleProvider::class,')
            ->toContain("'client_id' => env('GOOGLE_CLIENT_ID'),")
            ->and(file_get_contents(
                $this->tempBase . '/src/Authentication/Domain/SocialProviders/GoogleProvider.php'
            ))
            ->toContain("public const string NAME = 'google';");
    });

    it('prints the follow-up the command leaves and writes it as a checklist to delete when done', function (): void {
        $this->artisan('social-login-installer-fake')
            ->expectsOutputToContain(
                'Run php artisan migrate, then create a Google OAuth client and set GOOGLE_CLIENT_ID in .env'
            )
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/AUTH-SOCIAL-TODO.md'))
            ->toStartWith("# Social login setup checklist (backend)\n\n> Generated by `php artisan auth:setup`")
            ->toContain('then **delete this file**')
            ->toContain('docs/social-login.md')
            ->toContain("\n- [ ] **Run `php artisan migrate`**")
            ->toContain("\n- [ ] **Create a Google OAuth client**")
            ->toContain('**Authorized JavaScript origins**')
            ->toContain("\n- [ ] **Set the client id**")
            ->toContain('GOOGLE_CLIENT_ID=')
            ->toContain('Check it worked: ')
            ->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
    });
});
