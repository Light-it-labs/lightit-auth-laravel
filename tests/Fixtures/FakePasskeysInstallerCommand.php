<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

final class FakePasskeysInstallerCommand extends Command
{
    protected $signature = 'passkeys-installer-fake {--with-composer}';

    public function handle(): int
    {
        $installer = new PasskeysInstaller(
            $this,
            new ComposerInstaller($this),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        $this->option('with-composer') === true ? $installer->install() : $installer->writeFiles();

        return self::SUCCESS;
    }
}
