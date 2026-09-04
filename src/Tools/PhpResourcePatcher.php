<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use ParseError;

/**
 * Patches a foreign, consumer-authored `JsonResource::toArray()` (the current-user
 * resource `CurrentUserResourceLocator` finds) with the two entries the frontend
 * layer needs. Path-injected and Command-free, mirroring `TypeScriptPatcher`'s
 * anchor/verify/restore shape for the PHP side.
 */
final class PhpResourcePatcher
{
    private const MARKER = '// lightit-auth: roles and permissions';

    private const DEFAULT_INDENTATION = '            ';

    private const TOARRAY_PATTERN = '/function\s+toArray\s*\([^)]*\)\s*(?::\s*[?\w|]+\s*)?\{/';

    private const NEXT_FUNCTION_PATTERN = '/function\s+\w+\s*\(/';

    private const RETURN_ARRAY_PATTERN = '/return\s*\[/';

    /**
     * A regex is enough here — this doesn't need to be bulletproof against every
     * possible PHP array syntax, just the common `'key' => ...` shape a
     * `JsonResource::toArray()` normally uses.
     */
    private const CONFLICTING_KEY_PATTERN = '/[\'"](?:roles|permissions)[\'"]\s*=>/';

    public function addRolesAndPermissions(string $path): PhpResourcePatchOutcome
    {
        if (! file_exists($path)) {
            return PhpResourcePatchOutcome::Missing;
        }

        $original = file_get_contents($path);

        if ($original === false) {
            return PhpResourcePatchOutcome::Failed;
        }

        if ($this->alreadyPatched($original)) {
            return PhpResourcePatchOutcome::AlreadyApplied;
        }

        $bounds = $this->locateToArrayReturnArray($original);

        if ($bounds === null) {
            return PhpResourcePatchOutcome::AnchorNotFound;
        }

        // Appending a duplicate array key is legal PHP (last value wins) and would
        // silently change the consumer's existing response shape without them
        // knowing. A second read of the file cannot detect this after the fact,
        // since both keys are technically "present" either way — so it has to be
        // caught here, before anything is written.
        if ($this->hasConflictingKey($original, $bounds)) {
            return PhpResourcePatchOutcome::KeyAlreadyPresent;
        }

        $indentation = $this->firstEntryIndentation($original, $bounds['open']) ?? self::DEFAULT_INDENTATION;
        $insertion = "\n".$this->manualSnippet($indentation);
        $patched = substr_replace($original, $insertion, $bounds['open'] + 1, 0);

        if (file_put_contents($path, $patched) === false) {
            return $this->restore($path, $original);
        }

        $written = file_get_contents($path);

        if ($written === false || ! $this->landedInReturnArray($written)) {
            return $this->restore($path, $original);
        }

        return PhpResourcePatchOutcome::Patched;
    }

    /**
     * The exact text the patch writes — also what the TODO reports, so applied and
     * reported text can never drift.
     */
    public function manualSnippet(string $indentation = self::DEFAULT_INDENTATION): string
    {
        return implode("\n", [
            "{$indentation}".self::MARKER,
            "{$indentation}'roles' => \$this->roles->pluck('name')->all(),",
            "{$indentation}'permissions' => \$this->getAllPermissions()->pluck('name')->all(),",
        ]);
    }

    private function restore(string $path, string $original): PhpResourcePatchOutcome
    {
        return file_put_contents($path, $original) === false
            ? PhpResourcePatchOutcome::Corrupted
            : PhpResourcePatchOutcome::Failed;
    }

    private function alreadyPatched(string $contents): bool
    {
        return str_contains($contents, self::MARKER);
    }

    /**
     * @param  array{open: int, close: int}  $bounds
     */
    private function hasConflictingKey(string $contents, array $bounds): bool
    {
        $body = substr($contents, $bounds['open'] + 1, $bounds['close'] - $bounds['open'] - 1);

        return preg_match(self::CONFLICTING_KEY_PATTERN, $body) === 1;
    }

    /**
     * The written file has to prove the marker landed inside the specific array we
     * targeted, not just "somewhere in the file" — mirroring
     * `TypeScriptPatcher::landedInRetryList()`'s reasoning for PHP, backed by a real
     * tokenizer instead of a hand-rolled scanner.
     */
    private function landedInReturnArray(string $contents): bool
    {
        if (! str_contains($contents, self::MARKER)) {
            return false;
        }

        if (! $this->parses($contents)) {
            return false;
        }

        $bounds = $this->locateToArrayReturnArray($contents);

        if ($bounds === null) {
            return false;
        }

        $markerOffset = strpos($contents, self::MARKER);

        return $markerOffset !== false
            && $markerOffset > $bounds['open']
            && $markerOffset < $bounds['close'];
    }

    private function parses(string $contents): bool
    {
        try {
            $tokens = token_get_all($contents, TOKEN_PARSE);
        } catch (ParseError) {
            return false;
        }

        return $tokens !== [];
    }

    /**
     * Locates `toArray()`'s `return [` and the offsets of its opening and matching
     * closing brackets, bounded so the search cannot wander into some unrelated
     * array later in the file — the next named `function` after `toArray()`'s own
     * opening brace is the upper bound, mirroring how `TypeScriptPatcher` bounds
     * its own anchor searches.
     *
     * @return array{open: int, close: int}|null
     */
    private function locateToArrayReturnArray(string $contents): ?array
    {
        if (preg_match(self::TOARRAY_PATTERN, $contents, $methodMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $bodyStart = $methodMatch[0][1] + \strlen($methodMatch[0][0]);
        $upperBound = \strlen($contents);

        if (preg_match(self::NEXT_FUNCTION_PATTERN, $contents, $nextMatch, PREG_OFFSET_CAPTURE, $bodyStart) === 1) {
            $upperBound = $nextMatch[0][1];
        }

        $region = substr($contents, $bodyStart, $upperBound - $bodyStart);

        if (preg_match(self::RETURN_ARRAY_PATTERN, $region, $returnMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $openBracket = $bodyStart + $returnMatch[0][1] + \strlen($returnMatch[0][0]) - 1;
        $closeBracket = $this->matchingCloseBracket($contents, $openBracket);

        if ($closeBracket === null || $closeBracket > $upperBound) {
            return null;
        }

        return ['open' => $openBracket, 'close' => $closeBracket];
    }

    private function matchingCloseBracket(string $contents, int $openBracketOffset): ?int
    {
        $length = \strlen($contents);
        $depth = 0;
        $index = $openBracketOffset;

        while ($index < $length) {
            $character = $contents[$index];

            if ($character === '"' || $character === "'") {
                $index = $this->endOfString($contents, $index);

                continue;
            }

            if ($character === '[') {
                $depth++;
            } elseif ($character === ']') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }

            $index++;
        }

        return null;
    }

    private function endOfString(string $contents, int $start): int
    {
        $quote = $contents[$start];
        $length = \strlen($contents);
        $index = $start + 1;

        while ($index < $length) {
            if ($contents[$index] === '\\') {
                $index += 2;

                continue;
            }

            if ($contents[$index] === $quote) {
                return $index + 1;
            }

            $index++;
        }

        return $length;
    }

    /**
     * Reads the leading whitespace of the array's first existing entry line, if
     * there is one, so the inserted entries match the file's own formatting —
     * the same "read the file's own formatting" approach
     * `TypeScriptPatcher::lastEntryIndentation()` uses. Falls back to
     * `self::DEFAULT_INDENTATION` for a collapsed, single-line array body.
     */
    private function firstEntryIndentation(string $contents, int $openBracket): ?string
    {
        $rest = substr($contents, $openBracket + 1, 200);

        if (preg_match('/^\r?\n([ \t]*)\S/', $rest, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
