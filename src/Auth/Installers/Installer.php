<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class Installer
{
    public function __construct(protected Command $command) {}

    /**
     * @param array<string> $packages
     */
    public function requireComposerPackages(array $packages): bool
    {
        $command = array_merge(['composer', 'require'], $packages);

        $process = new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']);
        $process->setTimeout(null);

        $this->command->info("Running: " . implode(' ', $command));

        return $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->command->error($buffer);
            } else {
                $this->command->info($buffer);
            }
        }) === 0;
    }

    public function replaceInFile(string $search, string $replace, string $path): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("Unable to read file: $path");
        }

        file_put_contents(
            $path,
            str_replace($search, $replace, $content)
        );
    }

    public function appendToFile(string $path, string $content): void
    {
        $fileContent = file_get_contents($path);
        if ($fileContent === false) {
            throw new Exception("Unable to read file: $path");
        }

        file_put_contents(
            $path,
            preg_replace('/}$/', $content . PHP_EOL . '}', $fileContent)
        );
    }
}
