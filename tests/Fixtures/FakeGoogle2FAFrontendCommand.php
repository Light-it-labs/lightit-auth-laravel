<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\Google2FAFrontendInstaller;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tools\StubRenderer;

final class FakeGoogle2FAFrontendCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'google2fa-frontend-fake';

    public function __construct(private readonly string|null $frontendPath = null)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runThroughSetupOutput(function (SetupOutput $setupOutput): void {
            $manifest = new FrontendPackageManifest();

            $installer = new Google2FAFrontendInstaller(
                $setupOutput,
                new StubRenderer(),
                new FrontendProjectLocator($manifest),
                $manifest,
                sys_get_temp_dir() . '/lightit-2fa-laravel-root',
                $this->frontendPath,
            );

            $installer->install();
        });
    }
}
