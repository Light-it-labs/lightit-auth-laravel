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

final class FakeGoogle2FARoutesCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'google2fa-routes-fake';

    public function handle(): int
    {
        return $this->runThroughSetupOutput(static function (SetupOutput $setupOutput): void {
            $installer = new Google2FAInstaller(
                $setupOutput,
                new ComposerInstaller($setupOutput),
                new StubCopier(OriginMarker::resolved()),
            );

            $registerRoutes = new ReflectionMethod($installer, 'registerRoutes');
            $registerRoutes->setAccessible(true);
            $registerRoutes->invoke($installer);
        });
    }
}
