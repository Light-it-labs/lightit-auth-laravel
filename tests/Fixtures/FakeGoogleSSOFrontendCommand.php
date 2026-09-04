<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\GoogleSSOFrontendInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubRenderer;

final class FakeGoogleSSOFrontendCommand extends Command
{
    protected $name = 'google-sso-frontend-fake';

    public function __construct(private readonly ?string $frontendPath = null)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = new FrontendPackageManifest;

        $installer = new GoogleSSOFrontendInstaller(
            $this,
            new StubRenderer,
            new OriginMarker('0.0.0-test'),
            new FrontendProjectLocator($manifest),
            $manifest,
            sys_get_temp_dir().'/lightit-google-sso-laravel-root',
            $this->frontendPath,
        );

        $installer->install();

        return self::SUCCESS;
    }
}
