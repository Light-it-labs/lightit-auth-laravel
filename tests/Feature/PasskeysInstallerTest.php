<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

describe('PasskeysInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/passkeys-installer-' . bin2hex(random_bytes(6));
        mkdir($this->tempBase . '/routes', 0755, true);
        file_put_contents($this->tempBase . '/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->writtenFiles = [
            ...array_map(
                static fn (string $destination): string => 'src/Authentication/' . $destination,
                array_values(PasskeysInstaller::FILES),
            ),
            'database/migrations/2026_10_01_000000_create_passkeys_table.php',
            'config/passkeys.php',
            'routes/passkeys.php',
            'AUTH-PASSKEYS-TODO.md',
        ];

        Artisan::command('passkeys-installer-fake {--with-composer}', function (): void {
            $installer = new PasskeysInstaller(
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

    it('writes every passkey stub, the migration, the config, the routes and the TODO', function (): void {
        $this->artisan('passkeys-installer-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect($this->tempBase . '/' . $relative)->toBeFile();
            expect(file_get_contents($this->tempBase . '/' . $relative))->not->toContain('{{');
        }
    });

    it(
        'reports Skipped and leaves every file untouched on a second run, the app\'s own config included',
        function (): void {
            mkdir($this->tempBase . '/config');
            file_put_contents($this->tempBase . '/config/passkeys.php', "<?php\n\nreturn ['custom' => true];\n");
    
            $this->artisan('passkeys-installer-fake')
                ->expectsOutputToContain('Skipped config/passkeys.php')
                ->assertSuccessful();
    
            expect(file_get_contents($this->tempBase . '/config/passkeys.php'))
                ->toBe("<?php\n\nreturn ['custom' => true];\n");
    
            $before = [];
            foreach ($this->writtenFiles as $relative) {
                $before[$relative] = file_get_contents($this->tempBase . '/' . $relative);
            }
    
            $command = $this->artisan('passkeys-installer-fake');
            foreach ($this->writtenFiles as $relative) {
                $command->expectsOutputToContain('Skipped ' . $relative);
            }
            $command->assertSuccessful();
    
            foreach ($before as $relative => $contents) {
                expect(file_get_contents($this->tempBase . '/' . $relative))->toBe($contents);
            }
        }
    );

    it('skips the migration when the app already has it under another timestamp', function (): void {
        mkdir($this->tempBase . '/database/migrations', 0755, true);
        file_put_contents(
            $this->tempBase . '/database/migrations/2031_05_05_120000_create_passkeys_table.php',
            '<?php'
        );

        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain('Skipped database/migrations/2031_05_05_120000_create_passkeys_table.php')
            ->assertSuccessful();

        expect(glob($this->tempBase . '/database/migrations/*_create_passkeys_table.php'))->toHaveCount(1);
    });

    it('requires routes/passkeys.php from routes/api.php exactly once', function (): void {
        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain("Updated routes/api.php: require __DIR__ . '/passkeys.php';")
            ->assertSuccessful();
        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain('Passkey routes already required in routes/api.php')
            ->assertSuccessful();

        expect(
            substr_count(
                (string) file_get_contents($this->tempBase . '/routes/api.php'),
                "require __DIR__ . '/passkeys.php';"
            )
        )
            ->toBe(1);
    });

    it('aborts the setup when routes/api.php is left in an inconsistent state', function (): void {
        chmod($this->tempBase . '/routes/api.php', 0444);
        set_error_handler(static fn (): bool => true);

        try {
            expect(fn () => $this->artisan('passkeys-installer-fake')->run())
                ->toThrow(
                    SetupAbortedException::class,
                    "routes/api.php was left in an inconsistent state while adding require __DIR__ . '/passkeys.php';"
                );
        } finally {
            restore_error_handler();
            chmod($this->tempBase . '/routes/api.php', 0644);
        }
    })->skip(
        fn (): bool => ! function_exists('posix_getuid') || posix_getuid() === 0,
        'root bypasses file permissions'
    );

    it('aborts before writing anything when composer cannot require webauthn-lib', function (): void {
        file_put_contents($this->tempBase . '/composer.json', '{ not json');

        expect(fn () => $this->artisan('passkeys-installer-fake', ['--with-composer' => true])->run())
            ->toThrow(SetupAbortedException::class, 'Failed to install web-auth/webauthn-lib:^5.3');

        expect($this->tempBase . '/src')->not->toBeDirectory()
            ->and($this->tempBase . '/AUTH-PASSKEYS-TODO.md')->not->toBeFile();
    });

    it('prints the only follow-up the command leaves: migrate and the passkeys env', function (): void {
        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain(
                'Run php artisan migrate, then set PASSKEYS_RP_ID, PASSKEYS_ALLOWED_ORIGINS and '
                . 'PASSKEYS_USER_HANDLE_SECRET in .env'
            )
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/AUTH-PASSKEYS-TODO.md'))
            ->toContain('php artisan migrate')
            ->toContain('PASSKEYS_RP_ID=')
            ->toContain('PASSKEYS_ALLOWED_ORIGINS=')
            ->toContain('PASSKEYS_USER_HANDLE_SECRET')
            ->toContain("php -r 'echo bin2hex(random_bytes(32));'");
    });

    it('writes the TODO as a checklist to delete when done', function (): void {
        $this->artisan('passkeys-installer-fake')->assertSuccessful();

        $todo = (string) file_get_contents($this->tempBase . '/AUTH-PASSKEYS-TODO.md');

        expect($todo)
            ->toStartWith("# Passkeys setup checklist (backend)\n\n> Generated by `php artisan auth:setup`")
            ->toContain('then **delete this file**')
            ->toContain('docs/passkeys.md')
            ->toContain("\n- [ ] **Run `php artisan migrate`**")
            ->toContain("\n- [ ] **Set the relying party**")
            ->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
    });
});
