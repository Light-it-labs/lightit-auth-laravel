<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Closure;
use Lightitlabs\Console\ConsoleProfile;
use Lightitlabs\Console\SetupOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs an installer the way `auth:setup` does, verbose so the per-file lines are printed
 * and can be asserted.
 */
trait RunsInstallerThroughSetupOutput
{
    /**
     * @param Closure(SetupOutput): void $install
     */
    private function runThroughSetupOutput(Closure $install): int
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $setupOutput = new SetupOutput($this->output, new ConsoleProfile(null));
        $succeeded = $setupOutput->feature('Fixture', static fn () => $install($setupOutput));
        $setupOutput->summary();

        return $succeeded ? self::SUCCESS : self::FAILURE;
    }
}
