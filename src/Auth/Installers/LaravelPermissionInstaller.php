<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Contracts\SetupReporter;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;

final class LaravelPermissionInstaller implements AuthInstallerInterface
{
    public function __construct(
        private readonly Command $command,
        private readonly SetupReporter $reporter,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
    ) {}

    /**
     * @throws SetupAbortedException
     */
    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages([
            'spatie/laravel-permission',
        ])) {
            throw new SetupAbortedException('Failed to install spatie/laravel-permission');
        }

        $this->copyConfigFile();
        $this->clearCacheConfig();
        $this->copyMigration();
        $this->copyPackageFiles();
    }

    private function copyConfigFile(): void
    {
        $source = base_path('vendor/spatie/laravel-permission/config/permission.php');
        $destination = config_path('permission.php');

        if (! file_exists($source)) {
            throw new SetupAbortedException("Spatie config file not found at: {$source}");
        }

        $outcome = $this->stubCopier->copy($source, $destination);

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written('config/permission.php'),
            StubCopyOutcome::Skipped => $this->reporter->skipped('config/permission.php'),
        };
    }

    private function clearCacheConfig(): void
    {
        $this->command->callSilently('optimize:clear');
    }

    private function copyMigration(): void
    {
        $source = base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub');

        if (! file_exists($source)) {
            throw new SetupAbortedException("Spatie migration file not found at: {$source}");
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_permission_tables.php";
        $relativePath = "database/migrations/{$filename}";
        $destination = base_path($relativePath);

        $outcome = $this->stubCopier->copy($source, $destination);

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($relativePath),
            StubCopyOutcome::Skipped => $this->reporter->skipped($relativePath),
        };
    }

    private function copyPackageFiles(): void
    {
        $stubsPath = __DIR__.'/../../Stubs/LaravelPermissions';
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

            $outcome = $this->stubCopier->copy(
                $stubsPath.$stub,
                $targetPath
            );

            match ($outcome) {
                StubCopyOutcome::Written => $this->reporter->written($relativeTarget),
                StubCopyOutcome::Skipped => $this->reporter->skipped($relativeTarget),
            };
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
