<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Lightit\Console\LightitConsoleOutput;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

final class ComposerInstaller
{
    use LightitConsoleOutput;

    public function __construct(protected Command $command)
    {
        $this->initializeOutput($this->command);
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
        info('📦 Installing composer packages: ' . implode(', ', $packages));
        $this->command->line(str_repeat('-', 60));

        return $process->run(function ($type, $buffer): void {
            foreach (explode("\n", $buffer) as $line) {
                $line = trim($line);

                if ($line === '') {
                    return;
                }

                if (
                    str_contains($line, 'Installing')
                    || str_contains($line, 'Generating optimized autoload')
                    || str_contains($line, 'Writing lock file')
                    || str_contains($line, 'Package operations')
                    || str_contains($line, 'Nothing to install')
                    || str_contains($line, 'Extracting archive')) {
                    info("   💡 $line");
                }

                if ($type === Process::ERR) {
                    error($line);
                }
            }
        }) === 0;
    }
}
