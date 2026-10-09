<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

describe('Passkeys routes stub', function (): void {
    beforeEach(function (): void {
        $fixtureNamespace = 'Lightitlabs\Tests\Fixtures\PasskeyRouteConstraintsStub';
        $stub = (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/routes/passkeys.stub');

        preg_match_all('/^use Lightit\\\\Authentication\\\\App\\\\Controllers\\\\(\w+);/m', $stub, $controllers);

        $missing = array_filter(
            $controllers[1],
            static fn (string $controller): bool => ! class_exists("{$fixtureNamespace}\\{$controller}"),
        );
        $controllersFile = sys_get_temp_dir() . '/passkey-route-controllers-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($controllersFile, "<?php\n\nnamespace {$fixtureNamespace};\n\n" . implode("\n", array_map(
            static fn (string $controller): string => "final class {$controller} { public function __invoke(): void {} }",
            $missing,
        )));
        require $controllersFile;
        unlink($controllersFile);

        $routesFile = sys_get_temp_dir() . '/passkeys-routes-stub-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents(
            $routesFile,
            str_replace('use Lightit\Authentication\App\Controllers\\', "use {$fixtureNamespace}\\", $stub),
        );

        $this->router = new Router($this->app['events'], $this->app);
        Route::swap($this->router);
        $this->router->group([], $routesFile);
        unlink($routesFile);
    });

    it('routes a numeric passkey id to rename and delete', function (string $method, string $uri): void {
        $route = $this->router->getRoutes()->match(Request::create($uri, $method));

        expect($route->parameter('passkey'))->toBe('12');
    })->with([
        'rename' => ['PATCH', '/passkeys/12'],
        'delete' => ['POST', '/passkeys/12/delete'],
    ]);

    it('answers 404 for a non-numeric passkey id before binding', function (string $method, string $uri): void {
        expect(fn () => $this->router->getRoutes()->match(Request::create($uri, $method)))
            ->toThrow(NotFoundHttpException::class);
    })->with([
        'rename' => ['PATCH', '/passkeys/abc'],
        'delete' => ['POST', '/passkeys/abc/delete'],
    ]);
});
