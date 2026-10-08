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

        $output = '';

        $exitCode = $process->run(static function (string $type, string $buffer) use (&$output): void {
            $output .= $buffer;
        });

        if ($exitCode === 0) {
            return true;
        }

        $this->reporter->error(
            'composer require ' . implode(' ', $packages) . " exited with code {$exitCode}:",
            array_values(array_filter(
                array_map(rtrim(...), preg_split('/\R/', $output) ?: []),
                static fn (string $line): bool => trim($line) !== '',
            )),
        );

        return false;
    }
}
