<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FrontendUsageScanner
{
    private const SCANNED_EXTENSIONS = ['ts', 'tsx'];

    /**
     * @return list<string>
     */
    public function grep(string $root, string $pattern): array
    {
        $sourceDirectory = $root . '/src';

        if (! is_dir($sourceDirectory)) {
            return [];
        }

        $hits = [];

        /** @var SplFileInfo $file */
        foreach ($this->files($sourceDirectory) as $file) {
            if (! \in_array($file->getExtension(), self::SCANNED_EXTENSIONS, true)) {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $file->getPathname()), '/\\');

            foreach (explode("\n", $contents) as $index => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $hits[] = $relative . ':' . ($index + 1);
                }
            }
        }

        usort($hits, 'strnatcmp');

        return $hits;
    }

    /**
     * @param list<string> $hits
     */
    public function toMarkdownList(array $hits): string
    {
        if ($hits === []) {
            return '- None found.';
        }

        return implode("\n", array_map(static function (string $hit): string {
            return '- `' . $hit . '`';
        }, $hits));
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private function files(string $directory): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }
}
