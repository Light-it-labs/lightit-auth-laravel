<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use ReflectionMethod;

/**
 * Drives LaravelPermissionInstaller's file-writing steps through reflection, skipping
 * requirePackages() (it shells out to composer in the consuming project) and the
 * optimize:clear call.
 */
final class FakeLaravelPermissionInstallerCommand extends Command
{
    protected $signature = 'laravel-permission-installer-fake';

    private const STEPS = [
        'publishConfig',
        'copyMigration',
        'copyCatalog',
        'copyApiFiles',
        'registerRoutes',
        'writeChecklist',
        'warnIfUserModelLacksHasRoles',
        'warnIfCatalogLacksRoleManagement',
    ];

    public function handle(): int
    {
        $installer = new LaravelPermissionInstaller(
            $this,
            new ComposerInstaller($this),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        foreach (self::STEPS as $step) {
            (new ReflectionMethod($installer, $step))->invoke($installer);
        }

        return self::SUCCESS;
    }
}
