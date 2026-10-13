<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeLaravelPermissionCommand;

describe('LaravelPermissionInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/lightit-permission-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root . '/database/migrations');
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->root);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->root);
    });

    it('fails on a missing spatie file but still writes the role catalog and seeders, as main did', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionCommand());

        $this->artisan('laravel-permission-fake')
            ->expectsOutputToContain('✘ Fixture · failed')
            ->expectsOutputToContain('✘ Spatie config file not found at: ')
            ->expectsOutputToContain('✘ Spatie migration file not found at: ')
            ->expectsOutputToContain('Wrote src/Shared/Roles/RoleManagement.php')
            ->expectsOutputToContain('Wrote src/Shared/Permissions/UserPermissions.php')
            ->expectsOutputToContain('Wrote database/seeders/RoleSeeder.php')
            ->expectsOutputToContain('Wrote database/seeders/PermissionSeeder.php')
            ->assertFailed();

        expect($this->root . '/src/Shared/Roles/RoleManagement.php')->toBeFile()
            ->and($this->root . '/src/Shared/Permissions/UserPermissions.php')->toBeFile()
            ->and($this->root . '/database/seeders/RoleSeeder.php')->toBeFile()
            ->and($this->root . '/database/seeders/PermissionSeeder.php')->toBeFile();
    });

    it('skips the permission tables migration on a later re-run instead of writing a second one', function (): void {
        $spatie = $this->root . '/vendor/spatie/laravel-permission';
        File::ensureDirectoryExists($spatie . '/config');
        File::ensureDirectoryExists($this->root . '/config');
        File::ensureDirectoryExists($spatie . '/database/migrations');
        file_put_contents($spatie . '/config/permission.php', "<?php\n\nreturn [];\n");
        file_put_contents(
            $spatie . '/database/migrations/create_permission_tables.php.stub',
            "<?php\n\nreturn new class {};\n"
        );
        Artisan::registerCommand(new FakeLaravelPermissionCommand());

        $this->artisan('laravel-permission-fake')->assertSuccessful();

        $written = glob($this->root . '/database/migrations/*_create_permission_tables.php');
        expect($written)->toHaveCount(1);
        $migration = 'database/migrations/2020_01_01_000000_create_permission_tables.php';
        rename($written[0], $this->root . '/' . $migration);

        $this->artisan('laravel-permission-fake')
            ->expectsOutputToContain("Skipped {$migration}")
            ->assertSuccessful();

        expect(glob($this->root . '/database/migrations/*_create_permission_tables.php'))
            ->toBe([$this->root . '/' . $migration]);
    });
});
