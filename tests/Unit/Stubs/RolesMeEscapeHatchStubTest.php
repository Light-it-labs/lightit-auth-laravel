<?php

declare(strict_types=1);

describe('current user permissions escape hatch stubs', function (): void {
    it('declares the CurrentUserPermissionsController exactly', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/LaravelPermissions/Http/Controllers/CurrentUserPermissionsController.stub'
        );

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Lightit\Shared\Permissions\App\Controllers;

            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;

            class CurrentUserPermissionsController
            {
                public function __invoke(Request $request): JsonResponse
                {
                    return response()->json([
                        'roles' => $request->user()->roles->pluck('name')->all(),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name')->all(),
                    ]);
                }
            }

            PHP);
    });

    it('declares the roles-me route exactly, with no competing /me route', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/LaravelPermissions/routes/roles-me.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Shared\Permissions\App\Controllers\CurrentUserPermissionsController;

            /*
            |--------------------------------------------------------------------------
            | Current User Permissions Route (escape hatch)
            |--------------------------------------------------------------------------
            |
            | Generated only when no current-user resource could be found to patch.
            | Prefer patching your existing current-user resource instead - see
            | ROLES-PERMISSIONS-TODO.md.
            |
            */

            Route::middleware('auth:sanctum')
                ->get('me/permissions', CurrentUserPermissionsController::class);

            PHP);

        expect($stub)
            ->not->toContain("get('me',")
            ->not->toContain('get("me",')
            ->toContain("get('me/permissions',");
    });

    it('routes only to a controller LaravelPermissionInstaller copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/LaravelPermissions/routes/roles-me.stub');
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Auth/Installers/LaravelPermissionInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });
});
