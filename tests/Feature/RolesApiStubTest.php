<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Lightitlabs\Tests\Fixtures\RolesApiStub\HttpException;
use Lightitlabs\Tests\Fixtures\RolesApiStub\RoleSeeder;
use Lightitlabs\Tests\Fixtures\RolesApiStub\StubLoader;
use Lightitlabs\Tests\Fixtures\RolesApiStub\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

StubLoader::load(
    'Permissions/UserPermissions.stub',
    'Permissions/RolePermissions.stub',
    'Permissions/PermissionManagement.stub',
    'Roles/RoleManagement.stub',
    'Database/Seeders/PermissionSeeder.stub',
    'Database/Seeders/RoleSeeder.stub',
    'Domain/Exceptions/LastSuperAdminException.stub',
    'Domain/Exceptions/SuperAdminRoleChangeForbiddenException.stub',
    'Domain/Exceptions/RoleAssignmentForbiddenException.stub',
    'Domain/Actions/ListRolesAction.stub',
    'Domain/Actions/ListUsersWithRolesAction.stub',
    'Domain/Actions/SyncUserRolesAction.stub',
    'App/Requests/SyncUserRolesRequest.stub',
    'App/Resources/CurrentUserPermissionsResource.stub',
    'App/Resources/RoleResource.stub',
    'App/Resources/UserWithRolesResource.stub',
    'App/Controllers/ShowCurrentUserPermissionsController.stub',
    'App/Controllers/ListRolesController.stub',
    'App/Controllers/ListUsersWithRolesController.stub',
    'App/Controllers/SyncUserRolesController.stub',
);

function rolesUser(string ...$roles): User
{
    static $sequence = 0;
    $sequence++;

    $user = new User();
    $user->name = "Test User {$sequence}";
    $user->email = "user{$sequence}@example.com";
    $user->password = 'not-used';
    $user->saveOrFail();

    $user->assignRole($roles);

    return $user;
}

function checklistPhpLine(string $startsWith): string
{
    $checklist = (string) file_get_contents(__DIR__ . '/../../src/Stubs/LaravelPermissions/AUTH-ROLES-TODO.md.stub');

    foreach (explode("\n", $checklist) as $line) {
        if (str_starts_with($line, $startsWith)) {
            return $line;
        }
    }

    throw new RuntimeException("No checklist line starts with {$startsWith}");
}

describe('generated roles API', function (): void {
    beforeEach(function (): void {
        $this->app->register(PermissionServiceProvider::class);

        config([
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
            'auth.guards.sanctum' => ['driver' => 'sanctum-stand-in', 'provider' => null],
            'auth.providers.users' => ['driver' => 'eloquent', 'model' => User::class],
        ]);

        // Sanctum's stateful guard resolves the `web` session user and has no provider of its own.
        Auth::viaRequest('sanctum-stand-in', static fn (Request $request): mixed => Auth::guard('web')->user());

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (include __DIR__ . '/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

        (new RoleSeeder())->setContainer($this->app)->__invoke();

        // The boilerplate's ExceptionHandler: its HttpException subclasses render as the error
        // envelope, and a failed authorization (AccessDeniedHttpException once Laravel prepares it)
        // becomes ForbiddenException (code `forbidden`).
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->renderable(
            static fn (HttpException $exception) => response()->json(
                ['error' => ['code' => $exception->errorCode(), 'message' => $exception->getMessage()]],
                $exception->getStatusCode(),
            ),
        );
        $handler->renderable(
            static fn (AccessDeniedHttpException $exception) => response()->json(
                ['error' => ['code' => 'forbidden', 'message' => null]],
                403,
            ),
        );

        // Registered first and shaped like laravel-for-package 13.x's routes/api.php, where
        // whereNumber() is chained after group() and so does not constrain {user}.
        Route::middleware(SubstituteBindings::class)
            ->prefix('api/users')
            ->group(static function (): void {
                Route::prefix('{user}')
                    ->group(static function (): void {
                        Route::get('/', static fn (User $user): array => ['boilerplate_user' => $user->id]);
                    })
                    ->whereNumber('user');
            });

        Route::middleware(SubstituteBindings::class)
            ->prefix('api')
            ->group(StubLoader::renderToFile('routes/roles.stub'));
    });

    it('answers 401 to a guest on every roles route', function (): void {
        $this->getJson('/api/me/permissions')->assertUnauthorized();
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->putJson('/api/users/1/roles', ['roles' => []])->assertUnauthorized();
    });

    it('returns the signed-in user\'s roles and permissions from me/permissions', function (): void {
        $this->actingAs(rolesUser('admin'))
            ->getJson('/api/me/permissions')
            ->assertOk()
            ->assertExactJson(['data' => [
                'roles' => ['admin'],
                'permissions' => ['roles.manage', 'users.create', 'users.delete', 'users.get', 'users.list'],
            ]]);
    });

    it('returns empty lists from me/permissions for a user without roles', function (): void {
        $this->actingAs(rolesUser())
            ->getJson('/api/me/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['roles' => [], 'permissions' => []]]);
    });

    it('answers 403 on the management routes without roles.manage', function (): void {
        $target = rolesUser('user');

        $this->actingAs(rolesUser('user'));

        $this->getJson('/api/roles')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/roles/users')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->putJson("/api/users/{$target->id}/roles", ['roles' => ['admin']])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        expect($target->fresh()?->getRoleNames()->all())->toBe(['user']);
    });

    it('lists the roles of the app guard', function (): void {
        Role::create(['name' => 'auditor', 'guard_name' => 'api']);

        $this->actingAs(rolesUser('admin'))
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['admin', 'super-admin', 'user']);
    });

    it('lists users with their roles in a fixed number of queries', function (): void {
        $admin = rolesUser('admin');
        rolesUser('user');

        $this->actingAs($admin)->getJson('/api/roles/users')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/roles/users')
            ->assertOk()
            ->assertJsonPath('data.0.email_address', $admin->email)
            ->assertJsonPath('data.0.roles', ['admin'])
            ->assertJsonPath('data.1.roles', ['user'])
            ->assertJsonPath('meta.total', 2);
        $queriesForTwoUsers = count(DB::getQueryLog());

        rolesUser('user');
        rolesUser('admin', 'user');

        DB::flushQueryLog();
        $this->getJson('/api/roles/users')->assertOk()->assertJsonPath('meta.total', 4);

        expect(count(DB::getQueryLog()))->toBe($queriesForTwoUsers);
    });

    it('keeps the users listing out of the boilerplate\'s users/{user} routes', function (): void {
        $this->actingAs(rolesUser('admin'));

        $this->getJson('/api/users/roles')->assertNotFound();
        $this->getJson('/api/roles/users')->assertOk()->assertJsonPath('meta.total', 1);
    });

    it('replaces a user\'s roles and returns them', function (): void {
        $target = rolesUser('user');

        $this->actingAs(rolesUser('admin'))
            ->putJson("/api/users/{$target->id}/roles", ['roles' => ['admin', 'user']])
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.roles', ['admin', 'user']);

        expect($target->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['admin', 'user']);
    });

    it('removes every role with an empty list', function (): void {
        $target = rolesUser('admin');

        $this->actingAs(rolesUser('admin'))
            ->putJson("/api/users/{$target->id}/roles", ['roles' => []])
            ->assertOk()
            ->assertJsonPath('data.roles', []);

        expect($target->fresh()?->roles)->toBeEmpty();
    });

    it('rejects a missing list, an unknown role and a role from another guard', function (array $payload): void {
        Role::create(['name' => 'auditor', 'guard_name' => 'api']);
        $target = rolesUser('user');

        $this->actingAs(rolesUser('admin'))
            ->putJson("/api/users/{$target->id}/roles", $payload)
            ->assertUnprocessable();

        expect($target->fresh()?->getRoleNames()->all())->toBe(['user']);
    })->with([
        'missing' => [[]],
        'not a list' => [['roles' => 'admin']],
        'unknown role' => [['roles' => ['owner']]],
        'other guard' => [['roles' => ['auditor']]],
    ]);

    it('answers 404 for a user id that is not a number', function (): void {
        $this->actingAs(rolesUser('admin'))
            ->putJson('/api/users/me/roles', ['roles' => []])
            ->assertNotFound();
    });

    it('keeps the last super admin, even when they demote themselves', function (): void {
        $superAdmin = rolesUser('super-admin');

        $this->actingAs($superAdmin)
            ->putJson("/api/users/{$superAdmin->id}/roles", ['roles' => ['admin']])
            ->assertConflict()
            ->assertJsonPath('error.code', 'last_super_admin');

        expect($superAdmin->fresh()?->hasRole('super-admin'))->toBeTrue();
    });

    it('lets a super admin step down while another one remains', function (): void {
        $superAdmin = rolesUser('super-admin');
        rolesUser('super-admin');

        $this->actingAs($superAdmin)
            ->putJson("/api/users/{$superAdmin->id}/roles", ['roles' => ['admin']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['admin']);
    });

    it('lets only a super admin grant or revoke the super admin role', function (): void {
        $user = rolesUser('user');
        $superAdmin = rolesUser('super-admin');
        rolesUser('super-admin');

        $this->actingAs(rolesUser('admin'));

        $this->putJson("/api/users/{$user->id}/roles", ['roles' => ['super-admin']])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'super_admin_role_change_forbidden');
        $this->putJson("/api/users/{$superAdmin->id}/roles", ['roles' => ['admin']])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'super_admin_role_change_forbidden');
        $this->putJson("/api/users/{$superAdmin->id}/roles", ['roles' => ['super-admin', 'user']])
            ->assertOk();

        $this->actingAs($superAdmin)
            ->putJson("/api/users/{$user->id}/roles", ['roles' => ['super-admin']])
            ->assertOk();
    });

    it('lets an admin grant or revoke only roles whose permissions they hold', function (): void {
        Role::create(['name' => 'billing-admin'])->givePermissionTo(Permission::findOrCreate('billing.manage'));
        $user = rolesUser('user');
        $billingAdmin = rolesUser('billing-admin');

        $this->actingAs(rolesUser('admin'));

        $this->putJson("/api/users/{$user->id}/roles", ['roles' => ['billing-admin']])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'role_assignment_forbidden');
        $this->putJson("/api/users/{$billingAdmin->id}/roles", ['roles' => []])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'role_assignment_forbidden');
        $this->putJson("/api/users/{$billingAdmin->id}/roles", ['roles' => ['billing-admin', 'admin']])
            ->assertOk();

        expect($user->fresh()?->getRoleNames()->all())->toBe(['user']);

        $this->actingAs(rolesUser('super-admin'))
            ->putJson("/api/users/{$user->id}/roles", ['roles' => ['billing-admin']])
            ->assertOk();
    });

    it('lets a super admin through any gate once the checklist\'s Gate::before line is pasted', function (): void {
        Permission::findOrCreate('reports.export');
        $superAdmin = rolesUser('super-admin');
        $admin = rolesUser('admin');

        expect($superAdmin->can('reports.export'))->toBeFalse();

        eval(StubLoader::fixtureLine(checklistPhpLine('\Illuminate\Support\Facades\Gate::before(')));

        expect(Gate::forUser($superAdmin)->allows('reports.export'))->toBeTrue()
            ->and(Gate::forUser($admin)->allows('reports.export'))->toBeFalse();
    });

    it('gives a model roles with the checklist\'s HasRoles line', function (): void {
        $traitLine = checklistPhpLine('use \Spatie\Permission\Traits\HasRoles;');

        $name = 'ChecklistUser' . bin2hex(random_bytes(4));
        eval("final class {$name} extends \\Illuminate\\Foundation\\Auth\\User { {$traitLine} }");

        expect(class_uses_recursive($name))
            ->toContain(Spatie\Permission\Traits\HasRoles::class);
    });
});
