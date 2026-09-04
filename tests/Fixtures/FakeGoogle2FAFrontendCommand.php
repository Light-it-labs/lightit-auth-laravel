<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\Google2FAFrontendInstaller;
use Lightitlabs\Tools\StubRenderer;

final class FakeGoogle2FAFrontendCommand extends Command
{
    protected $name = 'google2fa-frontend-fake';

    public function __construct(private readonly ?string $frontendPath = null)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = new FrontendPackageManifest;

        $installer = new Google2FAFrontendInstaller(
            $this,
            new StubRenderer,
            new FrontendProjectLocator($manifest),
            $manifest,
            sys_get_temp_dir().'/lightit-2fa-laravel-root',
            $this->frontendPath,
        );

        $installer->install();

        return self::SUCCESS;
    }
}
