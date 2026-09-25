<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use ReflectionMethod;

/**
 * Drives Google2FAInstaller's file-writing steps directly through
 * reflection, skipping requirePackages() and publishConfiguration() - both
 * shell out to composer / PragmaRX's own vendor:publish, neither of which
 * belongs in this package's own composer.json (they're only ever required
 * inside the *consuming* project). Mirrors the pattern
 * Google2FAInstallerUserModelWarningTest uses for the same reason.
 */
final class FakeGoogle2FAInstallerCommand extends Command
{
    protected $signature = 'google2fa-installer-fake';

    private const STEPS = [
        'createAuthFiles',
        'warnIfUserModelCannotSupportTwoFactor',
        'copyMigration',
        'copyConfigFiles',
        'copyLangFiles',
        'registerRoutes',
        'writeManualIntegrationGuide',
    ];

    public function handle(): int
    {
        $installer = new Google2FAInstaller(
            $this,
            new ComposerInstaller($this),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        foreach (self::STEPS as $step) {
            $method = new ReflectionMethod($installer, $step);
            $method->setAccessible(true);
            $method->invoke($installer);
        }

        return self::SUCCESS;
    }
}
