<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Auth\Installers\SharedLoginFiles;

describe('Passkeys backend stubs', function (): void {
    it('imports only classes the installer writes or the boilerplate already has', function (): void {
        $known = [
            'Lightit\Users\Domain\Models\User',
            'Lightit\Users\App\Resources\UserResource',
            'Lightit\Shared\App\Exceptions\Http\HttpException',
            'Lightit\Shared\App\Exceptions\Http\UnauthenticatedException',
            ...array_map(
                static fn (string $destination): string => 'Lightit\Authentication\\'
                    . str_replace(['/', '.php'], ['\\', ''], $destination),
                [...array_values(PasskeysInstaller::FILES), ...array_values(SharedLoginFiles::FILES)],
            ),
        ];

        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            preg_match_all('/^use (Lightit\\\\[^;]+);/m', $file->getContents(), $imports);

            foreach ($imports[1] as $import) {
                expect($known)->toContain($import);
            }
        }
    });

    it('keeps every web-auth/webauthn-lib import inside PasskeyCeremonyService', function (): void {
        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            $importsLibrary = preg_match('/^use (Webauthn|Cose|ParagonIE)\\\\/m', $file->getContents()) === 1;

            expect($importsLibrary)->toBe($file->getFilename() === 'PasskeyCeremonyService.stub');
        }
    });

    it('never ships the removed Bearer login contract', function (): void {
        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            expect($file->getContents())
                ->not->toMatch('/\b(LoginDto|LoginResource|CredentialsDto)\b/')
                ->not->toContain('Bearer');
        }
    });

    it('answers a passkey sign-in with the UserResource', function (): void {
        $controller = (string) file_get_contents(
            PasskeysInstaller::stubDirectory() . '/Auth/Controllers/PasskeyLoginController.stub'
        );

        expect($controller)
            ->toContain('use Lightit\Users\App\Resources\UserResource;')
            ->toContain('return UserResource::make(');
    });

    it(
        'keeps the two sign-in routes public, each on its own limiter, and the account routes behind auth:sanctum',
        function (): void {
            $namespace = 'Lightitlabs\\Tests\\Fixtures\\PasskeysRoutesStub';
            $routes = (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/routes/passkeys.stub');
            preg_match_all(
                '/^use Lightit\\\\Authentication\\\\App\\\\Controllers\\\\(\\w+);$/m',
                $routes,
                $controllers
            );
    
            foreach ($controllers[1] as $controller) {
                if (! class_exists("{$namespace}\\{$controller}")) {
                    eval("namespace {$namespace}; final class {$controller} { public function __invoke(): void {} }");
                }
            }
    
            $tempFile = sys_get_temp_dir() . '/passkeys-routes-stub-' . bin2hex(random_bytes(6)) . '.php';
            file_put_contents(
                $tempFile,
                str_replace('use Lightit\\Authentication\\App\\Controllers\\', "use {$namespace}\\", $routes)
            );
            require $tempFile;
            unlink($tempFile);
    
            $middleware = [];
            foreach (Route::getRoutes()->getRoutes() as $route) {
                $middleware[$route->methods()[0] . ' ' . $route->uri()] = $route->middleware();
            }
    
            expect($middleware['POST auth/passkeys/login-options'])->toBe(['throttle:passkeys-sign-in-options'])
                ->and($middleware['POST auth/passkeys/login'])->toBe(['throttle:passkeys-sign-in']);
    
            expect($middleware['POST passkeys/registration-options'])->toContain('auth:sanctum')
                ->toContain('throttle:passkeys');
        }
    );
});
