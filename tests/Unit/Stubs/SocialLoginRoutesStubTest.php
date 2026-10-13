<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Lightitlabs\Auth\Installers\SocialLoginInstaller;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialLoginRequest;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\StubLoader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

StubLoader::load('DataTransferObjects/SocialLoginDto.stub', 'Requests/SocialLoginRequest.stub');

describe('Social login route stub', function (): void {
    beforeEach(function (): void {
        $fixtureNamespace = 'Lightitlabs\Tests\Fixtures\SocialLoginRouteStub';

        if (! class_exists("{$fixtureNamespace}\\SocialLoginController")) {
            $controllerFile = sys_get_temp_dir() . '/social-login-route-controller-' . bin2hex(
                random_bytes(6)
            ) . '.php';
            file_put_contents(
                $controllerFile,
                "<?php\n\nnamespace {$fixtureNamespace};\n\n"
                . 'final class SocialLoginController { public function __invoke(): void {} }',
            );
            require $controllerFile;
            unlink($controllerFile);
        }

        $routesFile = sys_get_temp_dir() . '/social-login-routes-stub-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($routesFile, str_replace(
            'use Lightit\Authentication\App\Controllers\\',
            "use {$fixtureNamespace}\\",
            (string) file_get_contents(SocialLoginInstaller::stubDirectory() . '/routes/social-login.stub'),
        ));

        $this->router = new Router($this->app['events'], $this->app);
        Route::swap($this->router);
        $this->router->group([], $routesFile);
        unlink($routesFile);
    });

    it('routes POST auth/social/{provider} through its own per-IP throttle', function (): void {
        $route = $this->router->getRoutes()->match(Request::create('/auth/social/google', 'POST'));

        expect($route->parameter('provider'))->toBe('google')
            ->and($route->middleware())->toBe(['throttle:social-login']);
    });

    it('answers 404 before any controller for a provider name that cannot be in the config', function (
        string $uri,
    ): void {
        expect(fn () => $this->router->getRoutes()->match(Request::create($uri, 'POST')))
            ->toThrow(NotFoundHttpException::class);
    })->with([
        'upper case' => ['/auth/social/Google'],
        'a config path' => ['/auth/social/google.client_id'],
    ]);
});

describe('SocialLoginRequest stub', function (): void {
    it('accepts only a non-empty string token', function (mixed $token, bool $passes): void {
        $request = new SocialLoginRequest();

        expect(Validator::make(['token' => $token], $request->rules())->passes())->toBe($passes);
    })->with([
        'a string' => ['eyJhbGciOiJSUzI1NiJ9.e30.c2ln', true],
        'missing' => [null, false],
        'empty' => ['', false],
        'an array' => [['eyJ'], false],
        'a number' => [123, false],
    ]);

    it('carries the provider from the route and the token from the body', function (): void {
        $request = SocialLoginRequest::create('/auth/social/google', 'POST', ['token' => 'a-token']);
        $request->setRouteResolver(static function () use ($request) {
            return (new Illuminate\Routing\Route('POST', 'auth/social/{provider}', []))->bind($request);
        });

        $dto = $request->toDto();

        expect($dto->provider)->toBe('google')
            ->and($dto->token)->toBe('a-token');
    });
});
