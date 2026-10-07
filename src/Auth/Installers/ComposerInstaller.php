<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\SetupReporter;
use Symfony\Component\Process\Process;

final class ComposerInstaller
{
    public function __construct(private readonly SetupReporter $reporter)
    {
    }

    /**
     * @param array<string> $packages
     */
    public function requirePackages(array $packages): bool
    {
        $process = new Process(
            array_merge(['composer', 'require', '--no-interaction'], $packages),
            base_path(),
            ['COMPOSER_MEMORY_LIMIT' => '-1'],
        );
        $process->setTimeout(null);

        if ($process->run() === 0) {
            return true;
        }

        $this->reporter->warning(
            'composer require ' . implode(' ', $packages) . " exited with code {$process->getExitCode()}. "
            . 'Run it yourself to see why.'
        );

        return false;
    }
}
