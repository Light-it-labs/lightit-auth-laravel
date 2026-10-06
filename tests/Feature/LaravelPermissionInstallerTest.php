<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeLaravelPermissionInstallerCommand;

describe('LaravelPermissionInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/laravel-permission-installer-' . bin2hex(random_bytes(6));
        $spatie = $this->tempBase . '/vendor/spatie/laravel-permission';

        File::ensureDirectoryExists($this->tempBase . '/routes');
        File::ensureDirectoryExists($this->tempBase . '/database/migrations');
        File::ensureDirectoryExists($spatie . '/config');
        File::ensureDirectoryExists($spatie . '/database/migrations');
        File::copy(
            __DIR__ . '/../../vendor/spatie/laravel-permission/config/permission.php',
            $spatie . '/config/permission.php'
        );
        File::copy(
            __DIR__ . '/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
            $spatie . '/database/migrations/create_permission_tables.php.stub',
        );
        File::put($this->tempBase . '/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");

        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->writtenFiles = [
            'config/permission.php',
            'src/Shared/Permissions/UserPermissions.php',
            'src/Shared/Permissions/RolePermissions.php',
            'src/Shared/Permissions/PermissionManagement.php',
            'src/Shared/Roles/RoleManagement.php',
            'database/seeders/PermissionSeeder.php',
            'database/seeders/RoleSeeder.php',
            'src/Roles/Domain/Exceptions/LastSuperAdminException.php',
            'src/Roles/Domain/Exceptions/SuperAdminRoleChangeForbiddenException.php',
            'src/Roles/Domain/Exceptions/RoleAssignmentForbiddenException.php',
            'src/Roles/Domain/Actions/ListRolesAction.php',
            'src/Roles/Domain/Actions/ListUsersWithRolesAction.php',
            'src/Roles/Domain/Actions/SyncUserRolesAction.php',
            'src/Roles/App/Requests/SyncUserRolesRequest.php',
            'src/Roles/App/Resources/CurrentUserPermissionsResource.php',
            'src/Roles/App/Resources/RoleResource.php',
            'src/Roles/App/Resources/UserWithRolesResource.php',
            'src/Roles/App/Controllers/ShowCurrentUserPermissionsController.php',
            'src/Roles/App/Controllers/ListRolesController.php',
            'src/Roles/App/Controllers/ListUsersWithRolesController.php',
            'src/Roles/App/Controllers/SyncUserRolesController.php',
            'routes/roles.php',
            'AUTH-ROLES-TODO.md',
        ];

        Artisan::registerCommand(new FakeLaravelPermissionInstallerCommand());
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->tempBase);
    });

    it('writes the catalog, the roles API, the routes file and the checklist', function (): void {
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect($this->tempBase . '/' . $relative)->toBeFile();
        }

        expect(File::glob($this->tempBase . '/database/migrations/*_create_permission_tables.php'))->toHaveCount(1);
    });

    it('leaves no placeholder unresolved in any written file', function (): void {
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(File::get($this->tempBase . '/' . $relative))->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
        }
    });

    it('requires the roles routes from routes/api.php once', function (): void {
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();

        expect(substr_count(File::get($this->tempBase . '/routes/api.php'), "require __DIR__ . '/roles.php';"))->toBe(
            1
        );
    });

    it('skips every file on a second run, the migration included', function (): void {
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();

        $pending = $this->artisan('laravel-permission-installer-fake');

        foreach ($this->writtenFiles as $relative) {
            $pending->expectsOutputToContain("Skipped {$relative}: the file already exists.");
        }

        $pending->doesntExpectOutputToContain('Created: ')->assertSuccessful();

        expect(File::glob($this->tempBase . '/database/migrations/*_create_permission_tables.php'))->toHaveCount(1);
    });

    it('never overwrites a file the consumer already has', function (): void {
        $catalog = $this->tempBase . '/src/Shared/Permissions/PermissionManagement.php';
        File::ensureDirectoryExists(dirname($catalog));
        File::put($catalog, "<?php\n// the consumer's own catalog\n");
        File::put($this->tempBase . '/database/migrations/2020_01_01_000000_create_permission_tables.php', "<?php\n");

        $this->artisan('laravel-permission-installer-fake')
            ->expectsOutputToContain(
                'Skipped src/Shared/Permissions/PermissionManagement.php: the file already exists.'
            )
            ->assertSuccessful();

        expect(File::get($catalog))->toBe("<?php\n// the consumer's own catalog\n")
            ->and(File::glob($this->tempBase . '/database/migrations/*_create_permission_tables.php'))->toHaveCount(1);
    });

    it('warns when a catalog from an earlier install does not grant role management', function (): void {
        $catalog = $this->tempBase . '/src/Shared/Permissions/PermissionManagement.php';
        File::ensureDirectoryExists(dirname($catalog));
        File::put($catalog, "<?php\n// UserPermissions only\n");

        $this->artisan('laravel-permission-installer-fake')
            ->expectsOutputToContain('does not list RolePermissions::MANAGE')
            ->assertSuccessful();
    });

    it('warns while the User model lacks HasRoles, and prints the manual steps', function (): void {
        require_once __DIR__ . '/../Fixtures/Google2FAUserModel/RealNamespaceNonConformingUser.php';

        $this->artisan('laravel-permission-installer-fake')
            ->expectsOutputToContain('Lightit\Users\Domain\Models\User does not use Spatie\Permission\Traits\HasRoles.')
            ->expectsOutputToContain(
                'Give \Lightit\Users\Domain\Models\User the Spatie\Permission\Traits\HasRoles trait.'
            )
            ->doesntExpectOutputToContain('does not list RolePermissions::MANAGE')
            ->assertSuccessful();
    });

    it('writes the checklist as one box per manual step, with the imports each snippet needs', function (): void {
        $this->artisan('laravel-permission-installer-fake')->assertSuccessful();

        $todo = File::get($this->tempBase . '/AUTH-ROLES-TODO.md');

        expect($todo)
            ->toStartWith("# Roles and permissions setup checklist (backend)\n\n> Generated by `auth:setup`.")
            ->toContain('https://github.com/Light-it-labs/lightit-auth-laravel/blob/main/docs/permission.md')
            ->toContain('`use Spatie\Permission\Traits\HasRoles;`')
            ->toContain('`src/Shared/App/Providers/AppServiceProvider.php`')
            ->toContain('`use Lightit\Shared\Roles\RoleManagement;`')
            ->toContain("\nGate::before(static fn (User \$user): bool|null")
            ->toContain('php artisan db:seed --class=RoleSeeder')
            ->toContain('->assignRole(\Lightit\Shared\Roles\RoleManagement::ROLE_SUPER_ADMIN);')
            ->toContain('Check it worked: ')
            ->and(substr_count($todo, "\n- [ ] "))->toBe(5);
    });
});
