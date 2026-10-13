<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use ReflectionMethod;

/**
 * Drives Google2FAInstaller's file-writing steps directly through
 * reflection, skipping requirePackages() - it shells out to composer, which
 * does not belong in this package's own composer.json (it's only ever
 * required inside the *consuming* project). Mirrors the pattern
 * Google2FAInstallerUserModelWarningTest uses for the same reason.
 */
final class FakeGoogle2FAInstallerCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'google2fa-installer-fake';

    private const STEPS = [
        'createAuthFiles',
        'warnIfUserModelCannotSupportTwoFactor',
        'copyMigration',
        'copyConfigFiles',
        'copyLangFiles',
        'registerRoutes',
        'writeManualIntegrationGuide',
        'warnIfLoginActionNotWired',
    ];

    public function handle(): int
    {
        return $this->runThroughSetupOutput(static function (SetupOutput $setupOutput): void {
            $installer = new Google2FAInstaller(
                $setupOutput,
                new ComposerInstaller($setupOutput),
                new StubCopier(new OriginMarker('0.0.0-test')),
            );

            foreach (self::STEPS as $step) {
                $method = new ReflectionMethod($installer, $step);
                $method->setAccessible(true);
                $method->invoke($installer);
            }
        });
    }
}
