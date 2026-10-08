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
            ->expectsOutputToContain('Wrote /Shared/Roles/RoleManagement.php')
            ->assertFailed();

        expect($this->root . '/src/Shared/Roles/RoleManagement.php')->toBeFile()
            ->and($this->root . '/src/Shared/Permissions/UserPermissions.php')->toBeFile()
            ->and($this->root . '/database/seeders/RoleSeeder.php')->toBeFile()
            ->and($this->root . '/database/seeders/PermissionSeeder.php')->toBeFile();
    });
});
