<?php

declare(strict_types=1);

describe('Passkeys routes stub', function (): void {
    it('declares the full registration and login route set, at the package\'s own paths', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Passkeys/routes/passkeys.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Authentication\App\Controllers\DeletePasskeyController;
            use Lightit\Authentication\App\Controllers\ListPasskeysController;
            use Lightit\Authentication\App\Controllers\PasskeyLoginController;
            use Lightit\Authentication\App\Controllers\StartPasskeyLoginController;
            use Lightit\Authentication\App\Controllers\StartPasskeyRegistrationController;
            use Lightit\Authentication\App\Controllers\StorePasskeyController;

            /*
            |--------------------------------------------------------------------------
            | Passkey (WebAuthn) Routes
            |--------------------------------------------------------------------------
            */

            Route::prefix('auth/passkey')
                ->middleware('throttle:6,1')
                ->group(static function (): void {
                    Route::post('options', StartPasskeyLoginController::class);
                    Route::post('/', PasskeyLoginController::class);
                });

            Route::prefix('passkeys')
                ->middleware('auth:sanctum')
                ->group(static function (): void {
                    Route::post('registration-options', StartPasskeyRegistrationController::class);
                    Route::get('/', ListPasskeysController::class);
                    Route::post('/', StorePasskeyController::class);
                    Route::delete('{passkey}', DeletePasskeyController::class);
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
