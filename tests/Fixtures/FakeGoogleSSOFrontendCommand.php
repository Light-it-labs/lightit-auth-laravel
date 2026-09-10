<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\TypeScriptPatcher;
use Lightitlabs\Auth\Installers\GoogleSSOFrontendInstaller;
use Lightitlabs\Tools\StubRenderer;

final class FakeGoogleSSOFrontendCommand extends Command
{
    protected $signature = 'google-sso-frontend-fake';

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
            new TypeScriptPatcher,
            new FrontendProjectLocator($manifest),
            sys_get_temp_dir().'/lightit-google-sso-laravel-root',
            $this->frontendPath,
        );

        $installer->install();

        return self::SUCCESS;
    }
}
