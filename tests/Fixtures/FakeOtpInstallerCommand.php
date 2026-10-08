<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

final class FakeOtpInstallerCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'otp-installer-fake';

    public function handle(): int
    {
        return $this->runThroughSetupOutput(static function (SetupOutput $setupOutput): void {
            (new OtpInstaller($setupOutput, new StubCopier(new OriginMarker('0.0.0-test'))))->install();
        });
    }
}
