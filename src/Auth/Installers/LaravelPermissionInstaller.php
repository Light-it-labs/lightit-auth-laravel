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
    private const MIGRATION_NAME = 'create_permission_tables';

    public function __construct(
        private readonly Command $command,
        private readonly SetupReporter $reporter,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
    ) {
    }

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
            $this->reporter->error("Spatie config file not found at: {$source}");

            return;
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
        $existing = glob(base_path('database/migrations/*_' . self::MIGRATION_NAME . '.php'));

        if ($existing !== false && $existing !== []) {
            $this->reporter->skipped('database/migrations/' . basename($existing[0]));

            return;
        }

        $source = base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub');

        if (! file_exists($source)) {
            $this->reporter->error("Spatie migration file not found at: {$source}");

            return;
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_" . self::MIGRATION_NAME . '.php';
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
        $stubsPath = __DIR__ . '/../../Stubs/LaravelPermissions';

        $files = [
            '/Permissions/UserPermissions.stub' => 'src/Shared/Permissions/UserPermissions.php',
            '/Permissions/PermissionManagement.stub' => 'src/Shared/Permissions/PermissionManagement.php',
            '/Roles/RoleManagement.stub' => 'src/Shared/Roles/RoleManagement.php',

            '/Database/Seeders/PermissionSeeder.stub' => 'database/seeders/PermissionSeeder.php',
            '/Database/Seeders/RoleSeeder.stub' => 'database/seeders/RoleSeeder.php',
        ];

        foreach ($files as $stub => $destination) {
            $targetPath = base_path($destination);

            $this->ensureDirectoryExists(dirname($targetPath));

            $outcome = $this->stubCopier->copy(
                $stubsPath . $stub,
                $targetPath
            );

            match ($outcome) {
                StubCopyOutcome::Written => $this->reporter->written($destination),
                StubCopyOutcome::Skipped => $this->reporter->skipped($destination),
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
