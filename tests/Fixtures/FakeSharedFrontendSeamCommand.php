<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\SharedFrontendSeamInstaller;
use Lightitlabs\Tools\StubRenderer;

final class FakeSharedFrontendSeamCommand extends Command
{
    protected $signature = 'shared-frontend-seam-fake';

    public function __construct(
        private readonly bool $needsSessionSeam,
        private readonly bool $needsPasskeysSecuritySection,
        private readonly bool $needsTwoFactorSecuritySection,
        private readonly ?string $frontendPath = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = new FrontendPackageManifest;

        $installer = new SharedFrontendSeamInstaller(
            $this,
            new StubRenderer,
            new FrontendProjectLocator($manifest),
            sys_get_temp_dir().'/lightit-shared-seam-laravel-root',
            $this->needsSessionSeam,
            $this->needsPasskeysSecuritySection,
            $this->needsTwoFactorSecuritySection,
            $this->frontendPath,
        );

        $installer->install();

        return self::SUCCESS;
    }
}
