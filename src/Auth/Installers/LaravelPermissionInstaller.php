<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Permissions\PermissionCatalog;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\CurrentUserResourceLocator;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\PhpResourcePatcher;
use Lightitlabs\Tools\PhpResourcePatchOutcome;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubRenderer;

final class LaravelPermissionInstaller implements AuthInstallerInterface
{
    private const ROUTES_LABEL = 'roles and permissions';

    private const ROUTES_FILE_NAME = 'roles.php';

    private const ESCAPE_HATCH_ROUTES_LABEL = 'current user permissions';

    private const ESCAPE_HATCH_ROUTES_FILE_NAME = 'roles-me.php';

    private const TODO_FILE = 'ROLES-PERMISSIONS-TODO.md';

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubRenderer $stubRenderer,
        private readonly OriginMarker $originMarker,
        private readonly PermissionCatalog $permissionCatalog = new PermissionCatalog,
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar,
        private readonly CurrentUserResourceLocator $currentUserResourceLocator = new CurrentUserResourceLocator,
        private readonly PhpResourcePatcher $phpResourcePatcher = new PhpResourcePatcher,
    ) {}

    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages([
            'spatie/laravel-permission',
        ])) {
            $this->command->error('Failed to install Laravel Permissions.');

            return;
        }

        $this->copyConfigFile();
        $this->clearCacheConfig();
        $this->copyMigrations();
        $this->copyPackageFiles();
        $this->registerRoutes();
        $this->patchCurrentUserResource();

        $this->composerInstaller->printSuccess('Laravel Permissions installed successfully!');
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(1, 6, 'Copying config files');

        $source = base_path('vendor/spatie/laravel-permission/config/permission.php');
        $destination = config_path('permission.php');

        if (! file_exists($source)) {
            $this->command->error("Spatie config file not found at: $source");

            return;
        }

        copy($source, $destination);
        $this->composerInstaller->printConfigPublished('Config file published: config/permission.php');
    }

    private function clearCacheConfig(): void
    {
        $this->composerInstaller->printStep(2, 6, 'Clearing cache config files');

        $this->command->call('optimize:clear');
    }

    private function copyMigrations(): void
    {
        $this->composerInstaller->printStep(3, 6, 'Copying Laravel Permission migration files');

        // Both migrations are named from a single captured instant so the additive
        // migration (+1s) always sorts after Spatie's table-creation migration
        // (+0s), even when both run within the same second.
        $now = time();

        $this->copySpatieMigration($now);
        $this->copyAdditiveMigration($now);
    }

    private function copySpatieMigration(int $now): void
    {
        $source = base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub');

        if (! file_exists($source)) {
            $this->command->error("Spatie migration file not found at: $source");

            return;
        }

        if ($this->migrationAlreadyExists('*_create_permission_tables.php')) {
            $this->composerInstaller->printMigrationCreated(
                'Skipped: a create_permission_tables migration already exists.'
            );

            return;
        }

        $filename = date('Y_m_d_His', $now).'_create_permission_tables.php';
        $relativePath = "database/migrations/{$filename}";

        copy($source, base_path($relativePath));

        $this->composerInstaller->printMigrationCreated("Migration copied to: {$relativePath}");
    }

    private function copyAdditiveMigration(int $now): void
    {
        if ($this->migrationAlreadyExists('*_add_group_to_permissions_and_description_to_roles.php')) {
            $this->composerInstaller->printMigrationCreated(
                'Skipped: an add_group_to_permissions_and_description_to_roles migration already exists.'
            );

            return;
        }

        $filename = date('Y_m_d_His', $now + 1).'_add_group_to_permissions_and_description_to_roles.php';
        $relativePath = "database/migrations/{$filename}";

        $this->stubRenderer->renderTo(
            __DIR__.'/../../Stubs/LaravelPermissions/Database/Migrations/add_group_to_permissions_and_description_to_roles.stub',
            base_path($relativePath),
            [],
            $this->originMarker
        );

        $this->composerInstaller->printMigrationCreated("Migration created: {$relativePath}");
    }

    private function migrationAlreadyExists(string $suffixPattern): bool
    {
        $matches = glob(base_path("database/migrations/{$suffixPattern}"));

        return $matches !== false && $matches !== [];
    }

    private function copyPackageFiles(): void
    {
        $this->composerInstaller->printStep(4, 6, 'Copying permission structure');

        $stubsPath = __DIR__.'/../../Stubs/LaravelPermissions';
        $srcBase = base_path('src');
        $seederBase = base_path('database/seeders');

        $tokenisedFiles = [
            '/Permissions/UserPermissions.stub' => [
                $srcBase, '/Shared/Permissions/UserPermissions.php',
                ['userPermissionConstants' => $this->permissionCatalog->toPhpConstants('UserPermissions')],
            ],
            '/Permissions/RolePermissions.stub' => [
                $srcBase, '/Shared/Permissions/RolePermissions.php',
                ['rolePermissionConstants' => $this->permissionCatalog->toPhpConstants('RolePermissions')],
            ],
            '/Permissions/PermissionManagement.stub' => [
                $srcBase, '/Shared/Permissions/PermissionManagement.php',
                ['permissionRegistry' => $this->permissionCatalog->toPhpRegistry()],
            ],
        ];

        $plainFiles = [
            '/Roles/RoleManagement.stub' => [$srcBase, '/Shared/Roles/RoleManagement.php'],
            '/Database/Seeders/PermissionSeeder.stub' => [$seederBase, '/PermissionSeeder.php'],
            '/Database/Seeders/RoleSeeder.stub' => [$seederBase, '/RoleSeeder.php'],
            '/Http/Controllers/ListRolesController.stub' => [$srcBase, '/Shared/Permissions/App/Controllers/ListRolesController.php'],
            '/Http/Controllers/ListPermissionsController.stub' => [$srcBase, '/Shared/Permissions/App/Controllers/ListPermissionsController.php'],
            '/Http/Controllers/ListPermissionGroupsController.stub' => [$srcBase, '/Shared/Permissions/App/Controllers/ListPermissionGroupsController.php'],
            '/Http/Controllers/UpdateUserRolesController.stub' => [$srcBase, '/Shared/Permissions/App/Controllers/UpdateUserRolesController.php'],
            '/Http/Requests/UpdateUserRolesRequest.stub' => [$srcBase, '/Shared/Permissions/App/Requests/UpdateUserRolesRequest.php'],
            '/Http/Resources/RoleResource.stub' => [$srcBase, '/Shared/Permissions/App/Resources/RoleResource.php'],
            '/Http/Resources/PermissionResource.stub' => [$srcBase, '/Shared/Permissions/App/Resources/PermissionResource.php'],
            '/Http/Resources/PermissionGroupResource.stub' => [$srcBase, '/Shared/Permissions/App/Resources/PermissionGroupResource.php'],
            '/Domain/Actions/ListRolesAction.stub' => [$srcBase, '/Shared/Permissions/Domain/Actions/ListRolesAction.php'],
            '/Domain/Actions/ListPermissionsAction.stub' => [$srcBase, '/Shared/Permissions/Domain/Actions/ListPermissionsAction.php'],
            '/Domain/Actions/ListPermissionGroupsAction.stub' => [$srcBase, '/Shared/Permissions/Domain/Actions/ListPermissionGroupsAction.php'],
            '/Domain/Actions/SyncUserRolesAction.stub' => [$srcBase, '/Shared/Permissions/Domain/Actions/SyncUserRolesAction.php'],
        ];

        foreach ($tokenisedFiles as $stub => [$basePath, $relativeTarget, $tokens]) {
            $this->stubRenderer->renderTo($stubsPath.$stub, "{$basePath}/{$relativeTarget}", $tokens, $this->originMarker);
            $this->composerInstaller->printFileCreated("Created: {$relativeTarget}");
        }

        foreach ($plainFiles as $stub => [$basePath, $relativeTarget]) {
            $this->stubRenderer->renderTo($stubsPath.$stub, "{$basePath}/{$relativeTarget}", [], $this->originMarker);
            $this->composerInstaller->printFileCreated("Created: {$relativeTarget}");
        }
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(5, 6, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/LaravelPermissions/routes/roles.stub',
            base_path('routes/'.self::ROUTES_FILE_NAME),
            'routes/'.self::ROUTES_FILE_NAME
        );

        $outcome = $this->routeFileRegistrar->register(
            base_path('routes/api.php'),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                "Updated routes/api.php: {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Roles and permissions routes already required in routes/api.php'
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find routes/api.php. '
                ."Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to routes/api.php automatically. "
                .'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                "routes/api.php was left in an inconsistent state while adding {$requireStatement}. "
                .'Please inspect the file.'
            ),
        };
    }

    /**
     * Per §4/G4 of the design doc, this is the primary path, not a fallback: no
     * package driver has ever generated a current-user resource, so every install
     * either patches a file this package did not author, or falls back to a
     * generated `GET /me/permissions` route.
     */
    private function patchCurrentUserResource(): void
    {
        $this->composerInstaller->printStep(6, 6, 'Patching the current-user resource');

        $resourcePath = $this->currentUserResourceLocator->locate(base_path());

        $currentUserPatchStatus = $resourcePath !== null
            ? $this->patchExistingResource($resourcePath)
            : $this->generateEscapeHatch();

        $this->writeTodo($currentUserPatchStatus);
    }

    private function patchExistingResource(string $resourcePath): string
    {
        $outcome = $this->phpResourcePatcher->addRolesAndPermissions($resourcePath);
        $relative = $this->relativeToBasePath($resourcePath);

        if ($outcome->needsManualStep()) {
            $this->command->warn(
                "Could not patch {$relative} automatically ({$outcome->name}). "
                .'Add the snippet from '.self::TODO_FILE.' manually.'
            );
        } else {
            $this->composerInstaller->printFileCreated(
                $outcome === PhpResourcePatchOutcome::Patched
                    ? "Patched {$relative} with roles and permissions."
                    : "{$relative} already carries the roles and permissions patch."
            );
        }

        return match ($outcome) {
            PhpResourcePatchOutcome::Patched => "- [x] `{$relative}` was patched automatically and now includes the snippet below.",
            PhpResourcePatchOutcome::AlreadyApplied => "- [x] `{$relative}` already carries the roles and permissions patch below.",
            PhpResourcePatchOutcome::KeyAlreadyPresent => "- [ ] `{$relative}` already declares a `roles` or `permissions` key with a "
                .'different shape - nothing was changed automatically. Reconcile it with the snippet below by hand.',
            PhpResourcePatchOutcome::AnchorNotFound,
            PhpResourcePatchOutcome::Missing,
            PhpResourcePatchOutcome::Failed,
            PhpResourcePatchOutcome::Corrupted => "- [ ] `{$relative}` was found but could not be patched automatically "
                ."({$outcome->name}). Add the snippet below to its `toArray()` yourself.",
        };
    }

    private function generateEscapeHatch(): string
    {
        $this->stubRenderer->renderTo(
            __DIR__.'/../../Stubs/LaravelPermissions/Http/Controllers/CurrentUserPermissionsController.stub',
            base_path('src/Shared/Permissions/App/Controllers/CurrentUserPermissionsController.php'),
            [],
            $this->originMarker
        );
        $this->composerInstaller->printFileCreated(
            'Created: src/Shared/Permissions/App/Controllers/CurrentUserPermissionsController.php'
        );

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/LaravelPermissions/routes/roles-me.stub',
            base_path('routes/'.self::ESCAPE_HATCH_ROUTES_FILE_NAME),
            'routes/'.self::ESCAPE_HATCH_ROUTES_FILE_NAME
        );

        $this->registerEscapeHatchRoute();

        return '- [ ] No current-user resource was found. A fallback route was generated instead: '
            .'`GET /me/permissions` (see `routes/roles-me.php`). Prefer patching your own current-user '
            .'resource and removing the fallback once you do - add the snippet below to its `toArray()`.';
    }

    private function registerEscapeHatchRoute(): void
    {
        $outcome = $this->routeFileRegistrar->register(
            base_path('routes/api.php'),
            self::ESCAPE_HATCH_ROUTES_FILE_NAME,
            self::ESCAPE_HATCH_ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ESCAPE_HATCH_ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                "Updated routes/api.php: {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Current user permissions routes already required in routes/api.php'
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find routes/api.php. '
                ."Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to routes/api.php automatically. "
                .'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                "routes/api.php was left in an inconsistent state while adding {$requireStatement}. "
                .'Please inspect the file.'
            ),
        };
    }

    private function writeTodo(string $currentUserPatchStatus): void
    {
        $this->stubRenderer->renderTo(
            __DIR__.'/../../Stubs/LaravelPermissions/ROLES-PERMISSIONS-TODO.md.stub',
            base_path(self::TODO_FILE),
            [
                'currentUserPatchStatus' => $currentUserPatchStatus,
                'manualSnippet' => $this->phpResourcePatcher->manualSnippet(),
            ],
            $this->originMarker
        );

        $this->composerInstaller->printFileCreated('Created: '.self::TODO_FILE);
    }

    private function relativeToBasePath(string $path): string
    {
        $base = rtrim(base_path(), '/\\').\DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, \strlen($base)) : $path;
    }

    /**
     * Skip-and-report if the destination already exists, mirroring
     * Google2FAInstaller::registerRoutes() — unlike copyPackageFiles()'s
     * unconditional overwrite, a generated route file is the one output a
     * consumer is expected to hand-edit afterwards, so re-running `auth:setup`
     * must not clobber it.
     */
    private function writeStubIfMissing(string $source, string $destination, string $label): void
    {
        if (file_exists($destination)) {
            $this->composerInstaller->printFileCreated("Skipped {$label}: the file already exists.");

            return;
        }

        $this->stubRenderer->renderTo($source, $destination, [], $this->originMarker);
        $this->composerInstaller->printFileCreated("Created: {$label}");
    }
}
