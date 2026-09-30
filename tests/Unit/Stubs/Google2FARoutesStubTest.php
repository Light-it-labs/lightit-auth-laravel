<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;

describe('Google2FA routes stub', function (): void {
    it('declares exactly the route set from docs/google-2fa.md, at the package\'s own paths', function (): void {
        $stub = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Authentication\App\Controllers\CompleteTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\ConfirmTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\DisableTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\EnableTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\RegenerateRecoveryCodesController;
            use Lightit\Authentication\App\Controllers\RequestTwoFactorResetController;
            use Lightit\Authentication\App\Controllers\ResetTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\SetupTwoFactorAuthenticationController;
            use Lightit\Authentication\App\Controllers\ShowTwoFactorAuthenticationStatusController;
            use Lightit\Authentication\App\Controllers\VerifyRecoveryCodeController;

            /*
            |--------------------------------------------------------------------------
            | Two-Factor Authentication Routes
            |--------------------------------------------------------------------------
            |
            | The `2fa` rate limiter these routes rely on is registered by the
            | lightit-auth-laravel package's own service provider, not here, so it
            | still runs when Laravel loads a cached route file (`route:cache`)
            | instead of executing this one.
            */

            Route::prefix('2fa')
                ->group(static function (): void {
                    Route::post('setup', SetupTwoFactorAuthenticationController::class)->middleware('throttle:2fa');
                    Route::post('complete', CompleteTwoFactorAuthenticationController::class)->middleware('throttle:2fa');
                    Route::post('verify-recovery-code', VerifyRecoveryCodeController::class)->middleware('throttle:2fa');
                    Route::post('reset', ResetTwoFactorAuthenticationController::class)->middleware('throttle:2fa');

                    Route::middleware('auth:sanctum')
                        ->group(static function (): void {
                            Route::get('status', ShowTwoFactorAuthenticationStatusController::class);
                            Route::post('enable', EnableTwoFactorAuthenticationController::class)->middleware('throttle:2fa');
                            Route::post('confirm', ConfirmTwoFactorAuthenticationController::class)->middleware('throttle:2fa');
                            Route::post('disable', DisableTwoFactorAuthenticationController::class)->middleware('throttle:2fa');
                            Route::post('regenerate-recovery-codes', RegenerateRecoveryCodesController::class)->middleware('throttle:2fa');
                            Route::post('request-reset', RequestTwoFactorResetController::class)->middleware('throttle:2fa');
                        });
                });

            PHP);
    });

    it('routes only to controllers Google2FAInstaller already copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');
        $installer = (string) file_get_contents(
            __DIR__ . '/../../../src/Auth/Installers/Google2FAInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });

    it(
        'matches every twoFactor*Endpoint token the frontend stubs rely on to a route segment in this stub',
        function (): void {
            $stub = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');
    
            $tokenSegments = [];
    
            foreach (FrontendStubTokens::defaults() as $token => $value) {
                if (! str_starts_with($token, 'twoFactor') || ! str_ends_with($token, 'Endpoint')) {
                    continue;
                }
    
                $tokenSegments[] = str_replace('2fa/', '', $value);
            }
    
            preg_match_all("/Route::(?:get|post)\('([^']+)'/", $stub, $matches);
            $routeSegments = $matches[1];
    
            sort($tokenSegments);
            sort($routeSegments);
    
            expect($tokenSegments)->not->toBeEmpty();
    
            expect($routeSegments)->toBe($tokenSegments);
        }
    );

    it('throttles every endpoint that checks a code or the account password', function (): void {
        $stub = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');

        $throttledRoutes = [
            'setup',
            'complete',
            'verify-recovery-code',
            'reset',
            'enable',
            'confirm',
            'disable',
            'regenerate-recovery-codes',
            'request-reset',
        ];

        foreach ($throttledRoutes as $route) {
            expect($stub)->toMatch(
                "/Route::post\\('{$route}', \\w+Controller::class\\)->middleware\\('throttle:2fa'\\);/"
            );
        }
    });

    it('does not register the 2fa rate limiter at file scope, since that breaks under route:cache', function (): void {
        $stub = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/routes/two-factor-auth.stub');

        // Laravel never executes route files under `route:cache`, so a file-scope
        // `RateLimiter::for()` call here would silently stop registering the `2fa`
        // limiter in production. Registration belongs to the package's own service
        // provider `boot()` instead - see LightitServiceProviderTest.
        expect($stub)
            ->not->toContain('TwoFactorRateLimiter::register()')
            ->not->toContain('use Lightit\Authentication\Domain\TwoFactorRateLimiter;');
    });
});
