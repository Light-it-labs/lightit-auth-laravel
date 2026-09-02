<?php

declare(strict_types=1);

describe('Google2FA routes stub', function (): void {
    it('declares exactly the route set from docs/google-2fa.md, at the package\'s own paths', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Authentication\App\Controllers\CompleteTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\DisableTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\RegenerateRecoveryCodesController;
            use Lightit\Authentication\App\Controllers\RequestTwoFactorResetController;
            use Lightit\Authentication\App\Controllers\ResetTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\SetupTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\VerifyRecoveryCodeController;

            /*
            |--------------------------------------------------------------------------
            | Two-Factor Authentication Routes
            |--------------------------------------------------------------------------
            */

            Route::prefix('2fa')
                ->group(static function (): void {
                    Route::post('setup', SetupTwoFactorAuthenticationController::class);
                    Route::post('complete', CompleteTwoFactorAuthenticationController::class);
                    Route::post('verify-recovery-code', VerifyRecoveryCodeController::class);
                    Route::post('reset', ResetTwoFactorAuthenticationController::class);

                    Route::middleware('auth:sanctum')
                        ->group(static function (): void {
                            Route::post('disable', DisableTwoFactorAuthenticationController::class);
                            Route::post('regenerate-recovery-codes', RegenerateRecoveryCodesController::class);
                            Route::post('request-reset', RequestTwoFactorResetController::class);
                        });
                });

            PHP);
    });

    it('routes only to controllers Google2FAInstaller already copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Auth/Installers/Google2FAInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });
});
