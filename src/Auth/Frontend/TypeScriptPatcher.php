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

        if ($written === false || ! $this->alreadyPatched($written)) {
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

        $replacement = rtrim($text, " \t\n\r")
            . "\n" . $this->lastEntryIndentation($text) . self::CONSTANT_NAME . ','
            . $this->trailingWhitespace($text);

        return substr_replace($contents, $replacement, $offset, strlen($text));
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
