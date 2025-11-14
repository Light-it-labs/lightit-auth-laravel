<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;

final class LaravelPermissionInstaller implements AuthInstallerInterface
{
    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

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
        $this->copyMigration();
        $this->copyPackageFiles();

        $this->composerInstaller->printSuccess('Laravel Permissions installed successfully!');
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(1, 4, 'Copying config files');

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
        $this->composerInstaller->printStep(2, 4, 'Clearing cache config files');

        $this->command->call('optimize:clear');
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, 4, 'Copying Laravel Permission migration file');

        $source = base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub');

        if (! file_exists($source)) {
            $this->command->error("Spatie migration file not found at: $source");

            return;
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_permission_tables.php";
        $relativePath = "database/migrations/{$filename}";
        $destination = base_path($relativePath);

        copy($source, $destination);

        $this->composerInstaller->printMigrationCreated("Migration copied to: {$relativePath}");
    }

    private function copyPackageFiles(): void
    {
        $this->composerInstaller->printStep(4, 4, 'Copying permission structure');

        $stubsPath = __DIR__ . '/../../Stubs/LaravelPermissions';
        $srcBase = base_path('src');
        $seederBase = base_path('database/seeders');

        $files = [
            '/Permissions/UserPermissions.stub' => [$srcBase, '/Shared/Permissions/UserPermissions.php'],
            '/Permissions/PermissionManagement.stub' => [$srcBase, '/Shared/Permissions/PermissionManagement.php'],
            '/Roles/RoleManagement.stub' => [$srcBase, '/Shared/Roles/RoleManagement.php'],

            '/Database/Seeders/PermissionSeeder.stub' => [$seederBase, '/PermissionSeeder.php'],
            '/Database/Seeders/RoleSeeder.stub' => [$seederBase, '/RoleSeeder.php'],
        ];

        foreach ($files as $stub => [$basePath, $relativeTarget]) {
            $targetPath = "{$basePath}/{$relativeTarget}";

            $this->ensureDirectoryExists(dirname($targetPath));

            copy(
                $stubsPath . $stub,
                $targetPath
            );

            $this->composerInstaller->printFileCreated("Created: {$relativeTarget}");
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
