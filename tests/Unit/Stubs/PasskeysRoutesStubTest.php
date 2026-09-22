<?php

declare(strict_types=1);

describe('Passkeys routes stub', function (): void {
    it('declares exactly the registration-half route set, at the package\'s own paths', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Passkeys/routes/passkeys.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Authentication\App\Controllers\StartPasskeyRegistrationController;
            use Lightit\Authentication\App\Controllers\StorePasskeyController;

            /*
            |--------------------------------------------------------------------------
            | Passkey (WebAuthn) Routes
            |--------------------------------------------------------------------------
            */

            Route::prefix('passkeys')
                ->middleware('auth:sanctum')
                ->group(static function (): void {
                    Route::post('registration-options', StartPasskeyRegistrationController::class);
                    Route::post('/', StorePasskeyController::class);
                });

            PHP);
    });

    it('routes only to controllers PasskeysInstaller already copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Passkeys/routes/passkeys.stub');
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Auth/Installers/PasskeysInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });
});
