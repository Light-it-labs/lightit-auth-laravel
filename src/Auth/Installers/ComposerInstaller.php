<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class ComposerInstaller
{
    public function __construct(protected Command $command)
    {
    }

    /**
     * @param array<string> $packages
     */
    public function requirePackages(array $packages): bool
    {
        $command = array_merge(['composer', 'require'], $packages);

        $process = new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']);
        $process->setTimeout(null);

        $this->command->info('Running: ' . implode(' ', $command));

        return $process->run(function ($type, $buffer): void {
            if ($type === Process::ERR) {
                $this->command->error($buffer);
            } else {
                $this->command->info($buffer);
            }
        }) === 0;
    }
}
