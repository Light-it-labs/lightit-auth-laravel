<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use ReflectionMethod;

final class FakeGoogle2FARoutesCommand extends Command
{
    protected $signature = 'google2fa-routes-fake';

    public function handle(): int
    {
        $installer = new Google2FAInstaller(
            $this,
            new ComposerInstaller($this),
            new StubCopier(OriginMarker::resolved()),
        );

        $registerRoutes = new ReflectionMethod($installer, 'registerRoutes');
        $registerRoutes->setAccessible(true);
        $registerRoutes->invoke($installer);

        return self::SUCCESS;
    }
}
