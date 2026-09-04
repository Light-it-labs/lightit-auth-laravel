<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use RuntimeException;

final class StubRenderer
{
    private const LEFTOVER_TOKEN_PATTERN = '/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/';

    private const PHP_HEADER = "<?php\n\n";

    /**
     * @param  array<string, string>  $tokens
     */
    public function render(string $stubPath, array $tokens, ?OriginMarker $marker = null): string
    {
        $contents = @file_get_contents($stubPath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read stub: {$stubPath}");
        }

        $rendered = strtr($contents, $this->replacements($tokens));

        if (preg_match(self::LEFTOVER_TOKEN_PATTERN, $rendered, $matches) === 1) {
            throw new RuntimeException(
                "Unresolved placeholder {$matches[0]} left in rendered stub: {$stubPath}"
            );
        }

        if ($marker instanceof OriginMarker) {
            $rendered = $this->withMarker($rendered, $stubPath, $marker);
        }

        return $rendered;
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public function renderTo(
        string $stubPath,
        string $destination,
        array $tokens,
        ?OriginMarker $marker = null,
    ): void {
        $rendered = $this->render($stubPath, $tokens, $marker);

        $directory = \dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        if (file_put_contents($destination, $rendered) === false) {
            throw new RuntimeException("Unable to write file: {$destination}");
        }
    }

    /**
     * A PHP file's `<?php` opening tag must stay the first bytes in the file — any
     * text before it is emitted as literal output the moment the file is
     * `require`d. A plain prepend is only safe for non-PHP output (`.ts`, `.md`);
     * for rendered PHP the marker has to land just after the opening tag, mirroring
     * `StubCopier`'s identical handling of the same constraint.
     */
    private function withMarker(string $rendered, string $stubPath, OriginMarker $marker): string
    {
        $comment = $marker->forStub($stubPath);

        if (str_ends_with($stubPath, '.md.stub')) {
            return "<!-- {$comment} -->\n\n{$rendered}";
        }

        if (str_starts_with($rendered, self::PHP_HEADER)) {
            return self::PHP_HEADER."// {$comment}\n\n".substr($rendered, strlen(self::PHP_HEADER));
        }

        return "// {$comment}\n\n{$rendered}";
    }

    /**
     * strtr() is deliberate: it is a single longest-match-first pass, so a token
     * whose value contains another token's placeholder cannot cascade. An array
     * str_replace() applies each pair in sequence over the already-replaced
     * subject and would corrupt that case silently.
     *
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    private function replacements(array $tokens): array
    {
        $replacements = [];

        foreach ($tokens as $name => $value) {
            $replacements['{{ '.$name.' }}'] = $value;
            $replacements['{{'.$name.'}}'] = $value;
        }

        return $replacements;
    }
}
