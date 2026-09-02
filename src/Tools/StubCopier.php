<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use RuntimeException;

final class StubCopier
{
    private const PHP_HEADER = "<?php\n\n";

    public function __construct(private readonly OriginMarker $originMarker) {}

    public function copy(string $source, string $destination): void
    {
        $contents = @file_get_contents($source);

        if ($contents === false) {
            throw new RuntimeException("Unable to read stub: {$source}");
        }

        $marker = $this->originMarker->forStub($source);

        $withMarker = str_starts_with($contents, self::PHP_HEADER)
            ? self::PHP_HEADER."// {$marker}\n\n".substr($contents, strlen(self::PHP_HEADER))
            : "// {$marker}\n".$contents;

        if (@file_put_contents($destination, $withMarker) === false) {
            throw new RuntimeException("Unable to write file: {$destination}");
        }
    }
}
