<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakePasskeysInstallerCommand;

describe('PasskeysInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/passkeys-installer-' . bin2hex(random_bytes(6));
        mkdir($this->tempBase . '/routes', 0755, true);
        file_put_contents($this->tempBase . '/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->writtenFiles = [
            'src/Authentication/Domain/PasskeyRateLimiter.php',
            'src/Authentication/Domain/PasskeyChallengeStore.php',
            'src/Authentication/Domain/Services/PasskeyCeremonyService.php',
            'src/Authentication/Domain/Models/Passkey.php',
            'src/Authentication/Domain/DataTransferObjects/StorePasskeyDto.php',
            'src/Authentication/Domain/DataTransferObjects/VerifiedPasskeyDto.php',
            'src/Authentication/Domain/Exceptions/PasskeyChallengeExpiredException.php',
            'src/Authentication/Domain/Exceptions/PasskeyRegistrationFailedException.php',
            'src/Authentication/Domain/Exceptions/PasskeyAlreadyRegisteredException.php',
            'src/Authentication/Domain/Actions/StartPasskeyRegistrationAction.php',
            'src/Authentication/Domain/Actions/StorePasskeyAction.php',
            'src/Authentication/Domain/Actions/ListPasskeysAction.php',
            'src/Authentication/Domain/Actions/RenamePasskeyAction.php',
            'src/Authentication/Domain/Actions/DeletePasskeyAction.php',
            'src/Authentication/App/Requests/StartPasskeyRegistrationRequest.php',
            'src/Authentication/App/Requests/StorePasskeyRequest.php',
            'src/Authentication/App/Requests/RenamePasskeyRequest.php',
            'src/Authentication/App/Requests/DeletePasskeyRequest.php',
            'src/Authentication/App/Resources/PasskeyResource.php',
            'src/Authentication/App/Resources/PasskeyRegistrationOptionsResource.php',
            'src/Authentication/App/Controllers/ListPasskeysController.php',
            'src/Authentication/App/Controllers/StartPasskeyRegistrationController.php',
            'src/Authentication/App/Controllers/StorePasskeyController.php',
            'src/Authentication/App/Controllers/RenamePasskeyController.php',
            'src/Authentication/App/Controllers/DeletePasskeyController.php',
            'database/migrations/2026_10_01_000000_create_passkeys_table.php',
            'config/passkeys.php',
            'routes/passkeys.php',
            'AUTH-PASSKEYS-TODO.md',
        ];

        Artisan::registerCommand(new FakePasskeysInstallerCommand());
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->tempBase);
    });

    it('writes every passkey stub, the migration, the config, the routes and the TODO', function (): void {
        $this->artisan('passkeys-installer-fake')->assertSuccessful();

        expect($this->writtenFiles)->toHaveCount(29);

        foreach ($this->writtenFiles as $relative) {
            expect($this->tempBase . '/' . $relative)->toBeFile();
            expect(file_get_contents($this->tempBase . '/' . $relative))->not->toContain('{{');
        }
    });

    it('reports Skipped and leaves every file untouched on a second run', function (): void {
        $this->artisan('passkeys-installer-fake')->assertSuccessful();

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
    });

    it('never overwrites a config/passkeys.php the app already has', function (): void {
        mkdir($this->tempBase . '/config');
        file_put_contents($this->tempBase . '/config/passkeys.php', "<?php\n\nreturn ['custom' => true];\n");

        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain('Skipped config/passkeys.php')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/config/passkeys.php'))
            ->toBe("<?php\n\nreturn ['custom' => true];\n");
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

    it('stops before writing anything when composer cannot require webauthn-lib', function (): void {
        file_put_contents($this->tempBase . '/composer.json', '{ not json');

        $this->artisan('passkeys-installer-fake', ['--with-composer' => true])
            ->expectsOutputToContain('Failed to install web-auth/webauthn-lib:^5.3')
            ->assertSuccessful();

        expect($this->tempBase . '/src')->not->toBeDirectory()
            ->and($this->tempBase . '/AUTH-PASSKEYS-TODO.md')->not->toBeFile();
    });

    it('prints the only follow-up the command leaves: migrate and the relying party env', function (): void {
        $this->artisan('passkeys-installer-fake')
            ->expectsOutputToContain(
                'Run php artisan migrate, then set PASSKEYS_RP_ID and PASSKEYS_ALLOWED_ORIGINS in .env'
            )
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/AUTH-PASSKEYS-TODO.md'))
            ->toContain('php artisan migrate')
            ->toContain('PASSKEYS_RP_ID=')
            ->toContain('PASSKEYS_ALLOWED_ORIGINS=')
            ->toContain('`\Lightit\Authentication\Domain\PasskeyRateLimiter`');
    });
});
