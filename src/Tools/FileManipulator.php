<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use Illuminate\Console\Command;

final class FileManipulator
{
    public function __construct(private readonly Command $command)
    {
    }

    public function replaceInFile(string $search, string $replace, string $path): void
    {
        if (! file_exists($path)) {
            $this->command->error("File not found: $path");

            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->command->error("Failed to read file: $path");

            return;
        }

        file_put_contents(
            $path,
            str_replace($search, $replace, $content)
        );
    }
}
