<?php

declare(strict_types=1);

describe('GoogleSSO routes stub', function (): void {
    it('declares the Google SSO login route at the package\'s own path', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/GoogleSSO/routes/google-sso.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Authentication\App\Controllers\GoogleLoginController;

            /*
            |--------------------------------------------------------------------------
            | Google SSO Routes
            |--------------------------------------------------------------------------
            */

            Route::post('auth/google', GoogleLoginController::class);

            PHP);
    });

    it('routes only to a controller GoogleSSOInstaller already copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/GoogleSSO/routes/google-sso.stub');
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Auth/Installers/GoogleSSOInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });
});
