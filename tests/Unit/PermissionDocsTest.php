<?php

declare(strict_types=1);

describe('docs/permission.md', function (): void {
    beforeEach(function (): void {
        $this->doc = (string) file_get_contents(__DIR__ . '/../../docs/permission.md');
        $this->stubs = __DIR__ . '/../../src/Stubs/LaravelPermissions/';
    });

    it('points to both checklists instead of repeating their steps', function (): void {
        expect($this->doc)
            ->toContain('### Install')
            ->toContain('**The steps live there, not on this page:**')
            ->toContain('**delete the file when every box is ticked**')
            ->toContain('| `AUTH-ROLES-TODO.md` |')
            ->toContain('| `AUTH-ROLES-FRONTEND-TODO.md` |')
            ->not->toMatch('/^#{2,4} \d+\./m');
    });

    it('documents every route the generated routes file registers', function (): void {
        $routesFile = (string) file_get_contents($this->stubs . 'routes/roles.stub');

        $documented = [
            'GET me/permissions' => "Route::get('me/permissions', ShowCurrentUserPermissionsController::class)",
            'GET roles' => "Route::get('/', ListRolesController::class)",
            'GET roles/users' => "Route::get('users', ListUsersWithRolesController::class)",
            'PUT users/{user}/roles' => "Route::put('users/{user}/roles', SyncUserRolesController::class)",
        ];

        expect(preg_match_all('/Route::(get|post|put|patch|delete)\(/', $routesFile))->toBe(count($documented))
            ->and($routesFile)->toContain("Route::prefix('roles')");

        foreach ($documented as $path => $definition) {
            expect($routesFile)->toContain($definition)
                ->and($this->doc)->toContain("| `{$path}` |");
        }
    });

    it('documents the error codes and the permission the generated code uses', function (): void {
        foreach (glob($this->stubs . 'Domain/Exceptions/*.stub') ?: [] as $exception) {
            preg_match("/errorCode = '([a-z_]+)'/", (string) file_get_contents($exception), $code);

            expect($this->doc)->toContain('`' . $code[1] . '`');
        }

        preg_match(
            "/MANAGE = '([a-z._]+)'/",
            (string) file_get_contents($this->stubs . 'Permissions/RolePermissions.stub'),
            $permission
        );

        expect($this->doc)->toContain('`' . $permission[1] . '`');
    });
});
