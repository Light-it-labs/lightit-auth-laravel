<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\GoogleSSOInstaller;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use ReflectionMethod;

/**
 * Drives GoogleSSOInstaller's file-writing steps directly through
 * reflection, skipping `install()`'s `requirePackages()` call - it shells
 * out to `composer require`, which does not belong in this package's own
 * test suite.
 */
final class FakeGoogleSSOInstallerFilesCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'google-sso-installer-files-fake';

    private const STEPS = [
        'createAuthFiles',
        'copySharedLoginFiles',
        'copySharedFiles',
    ];

    public function handle(): int
    {
        return $this->runThroughSetupOutput(static function (SetupOutput $setupOutput): void {
            $installer = new GoogleSSOInstaller(
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
