<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

final class LaravelPermissionInstaller implements AuthInstallerInterface
{
    private const PACKAGE = 'spatie/laravel-permission';

    private const TOTAL_STEPS = 7;

    private const STUBS_PATH = __DIR__ . '/../../Stubs/LaravelPermissions/';

    private const TODO_FILE = 'AUTH-ROLES-TODO.md';

    private const ROUTES_FILE_NAME = 'roles.php';

    private const ROUTES_LABEL = 'roles and permissions';

    private const API_ROUTES_PATH = 'routes/api.php';

    private const MIGRATION_SUFFIX = '_create_permission_tables.php';

    private const PERMISSION_CATALOG_PATH = 'src/Shared/Permissions/PermissionManagement.php';

    private const USER_MODEL_CLASS = 'Lightit\\Users\\Domain\\Models\\User';

    private const HAS_ROLES_TRAIT = 'Spatie\\Permission\\Traits\\HasRoles';

    private const CATALOG_FILES = [
        'Permissions/UserPermissions.stub' => 'src/Shared/Permissions/UserPermissions.php',
        'Permissions/RolePermissions.stub' => 'src/Shared/Permissions/RolePermissions.php',
        'Permissions/PermissionManagement.stub' => self::PERMISSION_CATALOG_PATH,
        'Roles/RoleManagement.stub' => 'src/Shared/Roles/RoleManagement.php',
        'Database/Seeders/PermissionSeeder.stub' => 'database/seeders/PermissionSeeder.php',
        'Database/Seeders/RoleSeeder.stub' => 'database/seeders/RoleSeeder.php',
    ];

    private const API_FILES = [
        'Domain/Exceptions/LastSuperAdminException.stub' => 'src/Roles/Domain/Exceptions/LastSuperAdminException.php',
        'Domain/Exceptions/SuperAdminRoleChangeForbiddenException.stub' => 'src/Roles/Domain/Exceptions/SuperAdminRoleChangeForbiddenException.php',
        'Domain/Exceptions/RoleAssignmentForbiddenException.stub' => 'src/Roles/Domain/Exceptions/RoleAssignmentForbiddenException.php',
        'Domain/Actions/ListRolesAction.stub' => 'src/Roles/Domain/Actions/ListRolesAction.php',
        'Domain/Actions/ListUsersWithRolesAction.stub' => 'src/Roles/Domain/Actions/ListUsersWithRolesAction.php',
        'Domain/Actions/SyncUserRolesAction.stub' => 'src/Roles/Domain/Actions/SyncUserRolesAction.php',
        'App/Requests/SyncUserRolesRequest.stub' => 'src/Roles/App/Requests/SyncUserRolesRequest.php',
        'App/Resources/CurrentUserPermissionsResource.stub' => 'src/Roles/App/Resources/CurrentUserPermissionsResource.php',
        'App/Resources/RoleResource.stub' => 'src/Roles/App/Resources/RoleResource.php',
        'App/Resources/UserWithRolesResource.stub' => 'src/Roles/App/Resources/UserWithRolesResource.php',
        'App/Controllers/ShowCurrentUserPermissionsController.stub' => 'src/Roles/App/Controllers/ShowCurrentUserPermissionsController.php',
        'App/Controllers/ListRolesController.stub' => 'src/Roles/App/Controllers/ListRolesController.php',
        'App/Controllers/ListUsersWithRolesController.stub' => 'src/Roles/App/Controllers/ListUsersWithRolesController.php',
        'App/Controllers/SyncUserRolesController.stub' => 'src/Roles/App/Controllers/SyncUserRolesController.php',
        'routes/roles.stub' => 'routes/' . self::ROUTES_FILE_NAME,
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly StubRenderer $stubRenderer = new StubRenderer(),
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar(),
    ) {
    }

    /**
     * @throws SetupAbortedException
     */
    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages([self::PACKAGE])) {
            throw new SetupAbortedException('Failed to install ' . self::PACKAGE);
        }

        $this->publishConfig();
        $this->clearCachedConfig();
        $this->copyMigration();
        $this->copyCatalog();
        $this->copyApiFiles();
        $this->registerRoutes();
        $this->writeChecklist();
        $this->warnIfUserModelLacksHasRoles();
        $this->warnIfCatalogLacksRoleManagement();

        $this->composerInstaller->printSuccess('Roles and permissions installed successfully!');
    }

    /**
     * @throws SetupAbortedException
     */
    private function publishConfig(): void
    {
        $this->composerInstaller->printStep(1, self::TOTAL_STEPS, 'Publishing the permission config');

        $source = base_path('vendor/spatie/laravel-permission/config/permission.php');

        if (! is_file($source)) {
            throw new SetupAbortedException("Spatie config file not found at: {$source}");
        }

        $this->copy($source, 'config/permission.php');
    }

    private function clearCachedConfig(): void
    {
        $this->composerInstaller->printStep(2, self::TOTAL_STEPS, 'Clearing the cached config');

        $this->command->call('optimize:clear');
    }

    /**
     * Spatie's migration gets a timestamped name, so a re-run looks for any earlier copy
     * instead of relying on the destination path to skip it.
     *
     * @throws SetupAbortedException
     */
    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, self::TOTAL_STEPS, 'Copying the permission tables migration');

        $existing = glob(base_path('database/migrations/*' . self::MIGRATION_SUFFIX));

        if ($existing !== false && $existing !== []) {
            $this->composerInstaller->printSkipped('database/migrations/' . basename($existing[0]));

            return;
        }

        $source = base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub');

        if (! is_file($source)) {
            throw new SetupAbortedException("Spatie migration file not found at: {$source}");
        }

        $this->copy($source, 'database/migrations/' . date('Y_m_d_His') . self::MIGRATION_SUFFIX);
    }

    private function copyCatalog(): void
    {
        $this->composerInstaller->printStep(4, self::TOTAL_STEPS, 'Copying the role and permission catalog');

        foreach (self::CATALOG_FILES as $stub => $destination) {
            $this->copy(self::STUBS_PATH . $stub, $destination);
        }
    }

    private function copyApiFiles(): void
    {
        $this->composerInstaller->printStep(5, self::TOTAL_STEPS, 'Copying the roles API');

        foreach (self::API_FILES as $stub => $destination) {
            $this->copy(self::STUBS_PATH . $stub, $destination);
        }
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(6, self::TOTAL_STEPS, 'Registering routes');

        $outcome = $this->routeFileRegistrar->register(
            base_path(self::API_ROUTES_PATH),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL,
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                'Updated ' . self::API_ROUTES_PATH . ": {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Roles routes already required in ' . self::API_ROUTES_PATH
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find ' . self::API_ROUTES_PATH . ". Add {$requireStatement} to your API route file."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to " . self::API_ROUTES_PATH . '. Add it by hand.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                self::API_ROUTES_PATH . " was left in an inconsistent state while adding {$requireStatement}. "
                . 'Inspect the file.'
            ),
        };
    }

    private function writeChecklist(): void
    {
        $this->composerInstaller->printStep(7, self::TOTAL_STEPS, 'Writing the setup checklist');

        $outcome = $this->stubRenderer->renderTo(
            self::STUBS_PATH . self::TODO_FILE . '.stub',
            base_path(self::TODO_FILE),
            [],
        );

        $this->report($outcome, self::TODO_FILE);

        $this->command->line('Finish the setup with the steps in ' . self::TODO_FILE . ':');
        $this->command->line(
            '  1. Add use \\' . self::HAS_ROLES_TRAIT . '; inside \\' . self::USER_MODEL_CLASS
            . ' (after use HasApiTokens;).'
        );
        $this->command->line(
            '  2. Add the Gate::before line for super admins at the end of '
            . '\\Lightit\\Shared\\App\\Providers\\AppServiceProvider::boot().'
        );
        $this->command->line('  3. Run vendor/bin/pint on those two files, to turn the full names into imports.');
        $this->command->line('  4. Run php artisan migrate, then php artisan db:seed --class=RoleSeeder.');
        $this->command->line('  5. Give your first user the super admin role.');
    }

    /**
     * Read-only: the package never edits a file it did not write, so it only warns. Skipped
     * while spatie isn't autoloadable yet (the run that just required it), because loading a
     * `User` that already uses `HasRoles` would then fail.
     */
    private function warnIfUserModelLacksHasRoles(): void
    {
        if (! trait_exists(self::HAS_ROLES_TRAIT)) {
            return;
        }

        if (! class_exists(self::USER_MODEL_CLASS)) {
            $this->command->warn(
                'Could not find ' . self::USER_MODEL_CLASS . '. The roles API needs it to use '
                . self::HAS_ROLES_TRAIT . ' - see ' . self::TODO_FILE . '.'
            );

            return;
        }

        if (in_array(self::HAS_ROLES_TRAIT, class_uses_recursive(self::USER_MODEL_CLASS), true)) {
            return;
        }

        $this->command->warn(
            self::USER_MODEL_CLASS . ' does not use ' . self::HAS_ROLES_TRAIT . '. Until it does, the roles '
            . 'endpoints fail - see ' . self::TODO_FILE . '.'
        );
    }

    /**
     * A catalog from an earlier install is never overwritten, so it can predate RolePermissions.
     */
    private function warnIfCatalogLacksRoleManagement(): void
    {
        $path = base_path(self::PERMISSION_CATALOG_PATH);
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false || str_contains($contents, 'RolePermissions::MANAGE')) {
            return;
        }

        $this->command->warn(
            self::PERMISSION_CATALOG_PATH . ' does not list RolePermissions::MANAGE. Add it to PERMISSIONS and to '
            . 'the admin role in RoleManagement::listFor(), then run php artisan db:seed --class=RoleSeeder. '
            . 'Until then only a super admin can manage roles.'
        );
    }

    private function copy(string $source, string $destination): void
    {
        $absolute = base_path($destination);
        $directory = \dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->report($this->stubCopier->copy($source, $absolute), $destination);
    }

    private function report(StubCopyOutcome $outcome, string $destination): void
    {
        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated("Created: {$destination}"),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped($destination),
        };
    }
}
