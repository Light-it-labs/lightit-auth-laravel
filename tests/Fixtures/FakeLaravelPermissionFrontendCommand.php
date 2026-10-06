<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\LaravelPermissionFrontendInstaller;
use Lightitlabs\Tools\StubRenderer;

final class FakeLaravelPermissionFrontendCommand extends Command
{
    protected $signature = 'laravel-permission-frontend-fake';

    public function __construct(private readonly string $frontendPath)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = new FrontendPackageManifest();

        (new LaravelPermissionFrontendInstaller(
            $this,
            new StubRenderer(),
            new FrontendProjectLocator($manifest),
            $manifest,
            sys_get_temp_dir() . '/lightit-roles-laravel-root',
            $this->frontendPath,
        ))->install();

        return self::SUCCESS;
    }
}
