<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use RuntimeException;

final class StubCopier
{
    /**
     * Matches the opening `<?php` tag line, whatever follows it on that same line: a bare tag
     * (`<?php` then newline), or a tag immediately followed by a statement (`<?php declare(...)`,
     * `<?php return [`).
     */
    private const PHP_OPEN_TAG_LINE_PATTERN = '/^<\?php[^\r\n]*\r?\n/';

    public function __construct(private readonly OriginMarker $originMarker) {}

    public function copy(string $source, string $destination): StubCopyOutcome
    {
        if (is_file($destination)) {
            return StubCopyOutcome::Skipped;
        }

        $contents = @file_get_contents($source);

        if ($contents === false) {
            throw new RuntimeException("Unable to read stub: {$source}");
        }

        $marker = $this->originMarker->forStub($source);

        $withMarker = preg_match(self::PHP_OPEN_TAG_LINE_PATTERN, $contents, $matches) === 1
            ? $matches[0]."// {$marker}\n".substr($contents, strlen($matches[0]))
            : "// {$marker}\n".$contents;

        if (@file_put_contents($destination, $withMarker) === false) {
            throw new RuntimeException("Unable to write file: {$destination}");
        }

        return StubCopyOutcome::Written;
    }
}
