<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use Illuminate\Console\Command;

final class FileManipulator
{
    public function __construct(private readonly Command $command)
    {
    }

    /**
     * Replaces the first occurrence of $search with $replace in $path.
     *
     * Returns false (and leaves the file untouched) when the file is missing,
     * unreadable, or $search does not occur in it, so callers can tell a
     * silent no-op apart from an actual write.
     */
    public function replaceInFile(string $search, string $replace, string $path): bool
    {
        if (! file_exists($path)) {
            $this->command->error("File not found: $path");

            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->command->error("Failed to read file: $path");

            return false;
        }

        $replaced = str_replace($search, $replace, $content, $count);

        if ($count === 0) {
            return false;
        }

        file_put_contents($path, $replaced);

        return true;
    }
}
