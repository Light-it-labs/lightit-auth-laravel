<?php

declare(strict_types=1);

describe('roles and permissions routes stub', function (): void {
    it('declares exactly the four W4 API-surface routes, at the package\'s own paths', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/LaravelPermissions/routes/roles.stub');

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use Lightit\Shared\Permissions\App\Controllers\ListPermissionGroupsController;
            use Lightit\Shared\Permissions\App\Controllers\ListPermissionsController;
            use Lightit\Shared\Permissions\App\Controllers\ListRolesController;
            use Lightit\Shared\Permissions\App\Controllers\UpdateUserRolesController;
            use Lightit\Shared\Permissions\RolePermissions;
            use Spatie\Permission\Middleware\PermissionMiddleware;

            /*
            |--------------------------------------------------------------------------
            | Roles and Permissions Routes
            |--------------------------------------------------------------------------
            */

            Route::middleware('auth:sanctum')
                ->group(static function (): void {
                    Route::get('roles', ListRolesController::class)
                        ->middleware(PermissionMiddleware::using(RolePermissions::READ_ROLE));

                    Route::prefix('permissions')
                        ->group(static function (): void {
                            Route::get('/', ListPermissionsController::class)
                                ->middleware(PermissionMiddleware::using(RolePermissions::READ_PERMISSION));
                            Route::get('groups', ListPermissionGroupsController::class)
                                ->middleware(PermissionMiddleware::using(RolePermissions::READ_PERMISSION));
                        });

                    Route::put('users/{user}/roles', UpdateUserRolesController::class)
                        ->middleware(PermissionMiddleware::using(RolePermissions::ASSIGN_ROLE));
                });

            PHP);
    });

    it('routes only to controllers LaravelPermissionInstaller already copies into the consuming project', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/LaravelPermissions/routes/roles.stub');
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Auth/Installers/LaravelPermissionInstaller.php'
        );

        preg_match_all('/(\w+Controller)::class/', $stub, $matches);

        foreach (array_unique($matches[1]) as $controller) {
            expect($installer)->toContain("{$controller}.stub");
        }
    });

    it('hardcodes auth:sanctum with no {{ authMiddleware }} detection', function (): void {
        $stub = (string) file_get_contents(__DIR__.'/../../../src/Stubs/LaravelPermissions/routes/roles.stub');

        expect($stub)
            ->toContain("Route::middleware('auth:sanctum')")
            ->not->toContain('{{')
            ->not->toContain('}}');
    });
});
