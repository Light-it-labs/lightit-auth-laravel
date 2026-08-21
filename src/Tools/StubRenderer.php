<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use RuntimeException;

final class StubRenderer
{
    private const LEFTOVER_TOKEN_PATTERN = '/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/';

    /**
     * @param array<string, string> $tokens
     */
    public function render(string $stubPath, array $tokens): string
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

        return $rendered;
    }

    /**
     * @param array<string, string> $tokens
     */
    public function renderTo(string $stubPath, string $destination, array $tokens): void
    {
        $rendered = $this->render($stubPath, $tokens);

        $directory = \dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        if (file_put_contents($destination, $rendered) === false) {
            throw new RuntimeException("Unable to write file: {$destination}");
        }
    }

    /**
     * strtr() is deliberate: it is a single longest-match-first pass, so a token
     * whose value contains another token's placeholder cannot cascade. An array
     * str_replace() applies each pair in sequence over the already-replaced
     * subject and would corrupt that case silently.
     *
     * @param array<string, string> $tokens
     *
     * @return array<string, string>
     */
    private function replacements(array $tokens): array
    {
        $replacements = [];

        foreach ($tokens as $name => $value) {
            $replacements['{{ ' . $name . ' }}'] = $value;
            $replacements['{{' . $name . '}}'] = $value;
        }

        return $replacements;
    }
}
