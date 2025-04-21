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

        $this->command->newLine();
        $this->command->info('📦 Installing composer packages: ' . implode(', ', $packages));
        $this->command->line(str_repeat('-', 60));

        return $process->run(function ($type, $buffer): void {
            foreach (explode("\n", $buffer) as $line) {
                $line = trim($line);

                if ($line === '') {
                    return;
                }

                if (str_contains($line, 'Installing')
                    || str_contains($line, 'Generating optimized autoload')
                    || str_contains($line, 'Writing lock file')
                    || str_contains($line, 'Package operations')
                    || str_contains($line, 'Nothing to install')
                    || str_contains($line, 'Extracting archive')) {
                    $this->command->line("   💡 $line");
                }

                if ($type === Process::ERR) {
                    $this->command->error($line);
                }
            }
        }) === 0;
    }
}
