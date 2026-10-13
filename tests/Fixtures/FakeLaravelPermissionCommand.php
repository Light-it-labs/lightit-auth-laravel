<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * Runs the whole install() with a composer that succeeds without installing anything, so
 * spatie's files are never in vendor/.
 */
final class FakeLaravelPermissionCommand extends Command
{
    use RunsInstallerThroughSetupOutput;

    protected $signature = 'laravel-permission-fake';

    public function handle(): int
    {
        return $this->runThroughSetupOutput(function (SetupOutput $setupOutput): void {
            (new LaravelPermissionInstaller(
                $this,
                $setupOutput,
                new ComposerInstaller($setupOutput, [PHP_BINARY, '-r', 'exit(0);', '--']),
                new StubCopier(new OriginMarker('0.0.0-test')),
            ))->install();
        });
    }
}
