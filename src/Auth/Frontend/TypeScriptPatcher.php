<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

final class TypeScriptPatcher
{
    private const CONSTANT_NAME = 'CSRF_TOKEN_MISMATCH';

    private const CONSTANT_DECLARATION = 'const CSRF_TOKEN_MISMATCH = 419;';

    private const LAST_IMPORT_PATTERN = '/^import [^\n]*;$/m';

    private const RETRY_STATUS_LIST_PATTERN = '/\[([^\[\]]*?)\]\s*\.includes\(\s*error\.response\.status\s*\)/s';

    public function addCsrfMismatchToRetryList(string $path): TypeScriptPatchOutcome
    {
        if (! file_exists($path)) {
            return TypeScriptPatchOutcome::Missing;
        }

        $original = file_get_contents($path);

        if ($original === false) {
            return TypeScriptPatchOutcome::Failed;
        }

        if ($this->alreadyPatched($original)) {
            return TypeScriptPatchOutcome::AlreadyApplied;
        }

        $patched = $this->insertConstant($original);

        if ($patched === null) {
            return TypeScriptPatchOutcome::AnchorNotFound;
        }

        $patched = $this->insertStatus($patched);

        if ($patched === null) {
            return TypeScriptPatchOutcome::AnchorNotFound;
        }

        if (file_put_contents($path, $patched) === false) {
            return $this->restore($path, $original);
        }

        $written = file_get_contents($path);

        if ($written === false || ! $this->landedInRetryList($written)) {
            return $this->restore($path, $original);
        }

        return TypeScriptPatchOutcome::Patched;
    }

    private function restore(string $path, string $original): TypeScriptPatchOutcome
    {
        return file_put_contents($path, $original) === false
            ? TypeScriptPatchOutcome::Corrupted
            : TypeScriptPatchOutcome::Failed;
    }

    private function alreadyPatched(string $contents): bool
    {
        return str_contains($contents, self::CONSTANT_NAME);
    }

    private function insertConstant(string $contents): string|null
    {
        $matches = [];

        if (preg_match_all(self::LAST_IMPORT_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return null;
        }

        $imports = $matches[0];

        if ($imports === []) {
            return null;
        }

        $last = end($imports);
        $insertAt = $last[1] + strlen($last[0]);

        if ($insertAt < 0) {
            return null;
        }

        return substr($contents, 0, $insertAt)
            . "\n\n" . self::CONSTANT_DECLARATION
            . substr($contents, $insertAt);
    }

    private function insertStatus(string $contents): string|null
    {
        $matches = [];

        if (preg_match(self::RETRY_STATUS_LIST_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $entries = $matches[1];
        $text = $entries[0];
        $offset = $entries[1];

        if ($offset < 0) {
            return null;
        }

        $lastCode = $this->lastCodeOffset($text);

        if ($lastCode === null) {
            return null;
        }

        // The list is text, not an AST, so the separating comma has to be placed by hand -
        // and after the last *code* character, never after a trailing line comment.
        $separated = $text[$lastCode] === ','
            ? $text
            : substr_replace($text, ',', $lastCode + 1, 0);

        $replacement = rtrim($separated, " \t\n\r")
            . $this->entrySeparator($text) . self::CONSTANT_NAME . $this->entryTerminator($text)
            . $this->trailingWhitespace($text);

        return substr_replace($contents, $replacement, $offset, strlen($text));
    }

    private function entrySeparator(string $entries): string
    {
        return str_contains($entries, "\n")
            ? "\n" . $this->lastEntryIndentation($entries)
            : ' ';
    }

    private function entryTerminator(string $entries): string
    {
        return str_contains($entries, "\n") ? ',' : '';
    }

    /**
     * Offset of the last character that is neither whitespace nor part of a comment.
     */
    private function lastCodeOffset(string $text): int|null
    {
        $length = \strlen($text);
        $last = null;
        $index = 0;

        while ($index < $length) {
            $character = $text[$index];
            $next = $index + 1 < $length ? $text[$index + 1] : '';

            if ($character === '/' && $next === '/') {
                $end = strpos($text, "\n", $index);
                $index = $end === false ? $length : $end;

                continue;
            }

            if ($character === '/' && $next === '*') {
                $end = strpos($text, '*/', $index + 2);
                $index = $end === false ? $length : $end + 2;

                continue;
            }

            if ($character === '"' || $character === "'" || $character === '`') {
                $index = $this->endOfString($text, $index);
                $last = $index - 1;

                continue;
            }

            if (trim($character) !== '') {
                $last = $index;
            }

            $index++;
        }

        return $last;
    }

    private function endOfString(string $text, int $start): int
    {
        $quote = $text[$start];
        $length = \strlen($text);
        $index = $start + 1;

        while ($index < $length) {
            if ($text[$index] === '\\') {
                $index += 2;

                continue;
            }

            if ($text[$index] === $quote) {
                return $index + 1;
            }

            $index++;
        }

        return $length;
    }

    /**
     * The written file has to prove the constant reached the retry list as its own entry.
     * Checking only that the name appears somewhere is satisfied by the insertion itself.
     */
    private function landedInRetryList(string $contents): bool
    {
        $matches = [];

        if (! str_contains($contents, self::CONSTANT_DECLARATION)) {
            return false;
        }

        if (preg_match(self::RETRY_STATUS_LIST_PATTERN, $contents, $matches) !== 1) {
            return false;
        }

        $entries = $this->withoutComments($matches[1]);

        if (! str_contains($entries, self::CONSTANT_NAME)) {
            return false;
        }

        foreach (explode(',', $entries) as $entry) {
            // Two identifiers separated by whitespace mean a comma went missing.
            if (preg_match('/[\w$\)\]]\s+[\w$]/', trim($entry)) === 1) {
                return false;
            }
        }

        return true;
    }

    private function withoutComments(string $entries): string
    {
        $stripped = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $entries);

        return $stripped ?? $entries;
    }

    private function lastEntryIndentation(string $entries): string
    {
        $lines = array_filter(
            explode("\n", rtrim($entries)),
            static fn (string $line): bool => trim($line) !== ''
        );

        if ($lines === []) {
            return '';
        }

        $last = (string) end($lines);

        return preg_match('/^([ \t]*)/', $last, $matches) === 1 ? $matches[1] : '';
    }

    private function trailingWhitespace(string $entries): string
    {
        return preg_match('/([ \t\n\r]*)$/', $entries, $matches) === 1 ? $matches[1] : '';
    }
}
