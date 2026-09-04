<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\PasskeysFrontendInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubRenderer;

final class FakePasskeysFrontendCommand extends Command
{
    protected $name = 'passkeys-frontend-fake';

    public function __construct(private readonly ?string $frontendPath = null)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = new FrontendPackageManifest;

        $installer = new PasskeysFrontendInstaller(
            $this,
            new StubRenderer,
            new OriginMarker('0.0.0-test'),
            new FrontendProjectLocator($manifest),
            $manifest,
            sys_get_temp_dir().'/lightit-passkeys-laravel-root',
            $this->frontendPath,
        );

        $installer->install();

        return self::SUCCESS;
    }
}
