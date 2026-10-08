<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Tests\Fixtures\RecordingSetupReporter;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

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

    it('reports each missing spatie file as an error and still writes the role catalog and seeders', function (): void {
        $reporter = new RecordingSetupReporter();
        $installer = new LaravelPermissionInstaller(
            new class() extends Command {
            },
            $reporter,
            new ComposerInstaller($reporter),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        foreach (['copyConfigFile', 'copyMigration', 'copyPackageFiles'] as $step) {
            (new ReflectionMethod($installer, $step))->invoke($installer);
        }

        expect($reporter->errors)->toHaveCount(2)
            ->and($reporter->errors[0])->toStartWith('Spatie config file not found at: ')
            ->and($reporter->errors[1])->toStartWith('Spatie migration file not found at: ')
            ->and($this->root . '/src/Shared/Roles/RoleManagement.php')->toBeFile()
            ->and($this->root . '/database/seeders/RoleSeeder.php')->toBeFile();
    });
});
