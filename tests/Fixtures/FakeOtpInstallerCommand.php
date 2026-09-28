<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * Drives OtpInstaller::install() through a real Artisan command run, so
 * `ComposerInstaller`'s `printStep()`/`printBoxedMessage()` calls have a
 * console output to write to - constructing the installer's command
 * directly (bypassing Artisan) leaves `$command->output` null and every
 * `line()` call fatals.
 */
final class FakeOtpInstallerCommand extends Command
{
    protected $signature = 'otp-installer-fake';

    public function handle(): int
    {
        $installer = new OtpInstaller(
            new ComposerInstaller($this),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        $installer->install();

        return self::SUCCESS;
    }
}
