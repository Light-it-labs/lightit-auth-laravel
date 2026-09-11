<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

/**
 * Wires the 2FA challenge gate into a login this package never generated -
 * e.g. `Light-it-labs/laravel`'s own cookie-based `LoginAction`, which takes
 * `Closure $onFailure, Closure $onSuccess` for rate limiting and calls
 * `$request->session()->regenerate()` itself. `LoginActionPatcher` only
 * recognises this package's own generated shapes; anything else is a file
 * this package did not author, so a whole-file replace (or giving up) would
 * either destroy behaviour it never wrote, or leave 2FA silently unwired.
 *
 * Inserts a single call instead - right after the method's own success path
 * clears the rate limiter and before it returns the authenticated user -
 * mirroring `PhpResourcePatcher`'s anchor/verify/restore shape for a
 * consumer-authored file, but anchored on this specific, known method shape
 * rather than a general one: `execute(Request, array, Closure $onFailure,
 * Closure $onSuccess): User`. A shape that doesn't match this anchor is
 * reported, never guessed at.
 */
final class NativeLoginActionInjector
{
    private const MARKER = '// lightit-auth: 2fa challenge gate';

    private const EXECUTE_METHOD_PATTERN = '/function\s+execute\s*\([^)]*Closure\s+\$onFailure[^)]*Closure\s+\$onSuccess[^)]*\)\s*:\s*User\s*\{/';

    private const ON_SUCCESS_CALL_PATTERN = '/\$onSuccess\s*\(\s*\)\s*;/';

    private const RETURN_STATEMENT_PATTERN = '/\n([ \t]*)return\s+(\$[A-Za-z_]\w*)\s*;/';

    public function inject(string $destination): NativeLoginInjectionOutcome
    {
        if (! file_exists($destination)) {
            return NativeLoginInjectionOutcome::AnchorNotFound;
        }

        $original = file_get_contents($destination);

        if ($original === false) {
            return NativeLoginInjectionOutcome::Failed;
        }

        if ($this->landedInMethodBody($original)) {
            return NativeLoginInjectionOutcome::AlreadyApplied;
        }

        $insertion = $this->locateInsertionPoint($original);

        if ($insertion === null) {
            return NativeLoginInjectionOutcome::AnchorNotFound;
        }

        $snippet = $this->snippet($insertion['indentation'], $insertion['variable']);
        $patched = substr_replace($original, $snippet, $insertion['offset'], 0);

        if (file_put_contents($destination, $patched) === false) {
            return $this->restore($destination, $original);
        }

        $written = file_get_contents($destination);

        if ($written === false || ! $this->landedInMethodBody($written)) {
            return $this->restore($destination, $original);
        }

        return NativeLoginInjectionOutcome::Patched;
    }

    /**
     * The exact call the injection adds - also what a manual-step warning
     * reports, so applied and reported text can never drift.
     */
    public function manualSnippet(string $indentation = '        '): string
    {
        return $this->guardCall($indentation, '$user');
    }

    private function restore(string $destination, string $original): NativeLoginInjectionOutcome
    {
        return file_put_contents($destination, $original) === false
            ? NativeLoginInjectionOutcome::Corrupted
            : NativeLoginInjectionOutcome::Failed;
    }

    /**
     * @return array{offset: int, indentation: string, variable: string}|null
     */
    private function locateInsertionPoint(string $contents): ?array
    {
        $bounds = $this->locateExecuteMethodBody($contents);

        if ($bounds === null) {
            return null;
        }

        $body = substr($contents, $bounds['open'], $bounds['close'] - $bounds['open']);

        if (preg_match(self::ON_SUCCESS_CALL_PATTERN, $body, $onSuccessMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $searchFrom = $onSuccessMatch[0][1] + \strlen($onSuccessMatch[0][0]);
        $region = substr($body, $searchFrom);

        if (preg_match_all(self::RETURN_STATEMENT_PATTERN, $region, $returnMatches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $lastIndex = \count($returnMatches[0]) - 1;
        // +1 skips the pattern's leading `\n` so the snippet lands on its own
        // line, right before the return statement's own indentation.
        $offsetInRegion = $returnMatches[0][$lastIndex][1] + 1;

        return [
            'offset' => $bounds['open'] + $searchFrom + $offsetInRegion,
            'indentation' => $returnMatches[1][$lastIndex][0],
            'variable' => $returnMatches[2][$lastIndex][0],
        ];
    }

    private function snippet(string $indentation, string $variable): string
    {
        return $this->guardCall($indentation, $variable)."\n";
    }

    private function guardCall(string $indentation, string $variable): string
    {
        return "{$indentation}".self::MARKER."\n"
            ."{$indentation}app(\\Lightit\\Authentication\\Domain\\Actions\\TwoFactorLoginGate::class)"
            ."->guardAgainstChallenge({$variable});\n";
    }

    /**
     * The written file has to prove the marker landed inside the specific
     * `execute()` this injector targets, not just "somewhere in the file" -
     * mirroring `PhpResourcePatcher::landedInReturnArray()`'s reasoning.
     */
    private function landedInMethodBody(string $contents): bool
    {
        if (! str_contains($contents, self::MARKER)) {
            return false;
        }

        $bounds = $this->locateExecuteMethodBody($contents);

        if ($bounds === null) {
            return false;
        }

        $markerOffset = strpos($contents, self::MARKER, $bounds['open']);

        return $markerOffset !== false && $markerOffset < $bounds['close'];
    }

    /**
     * Locates the `execute(...): User` method this injector targets and the
     * offsets of its opening and matching closing braces, bounded to that
     * method alone so the search cannot wander into some unrelated method
     * later in the file.
     *
     * @return array{open: int, close: int}|null
     */
    private function locateExecuteMethodBody(string $contents): ?array
    {
        if (preg_match(self::EXECUTE_METHOD_PATTERN, $contents, $methodMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $openBrace = $methodMatch[0][1] + \strlen($methodMatch[0][0]) - 1;
        $closeBrace = $this->matchingCloseBrace($contents, $openBrace);

        if ($closeBrace === null) {
            return null;
        }

        return ['open' => $openBrace, 'close' => $closeBrace];
    }

    private function matchingCloseBrace(string $contents, int $openBraceOffset): ?int
    {
        $length = \strlen($contents);
        $depth = 0;
        $index = $openBraceOffset;

        while ($index < $length) {
            $character = $contents[$index];

            if ($character === '"' || $character === "'") {
                $index = $this->endOfString($contents, $index);

                continue;
            }

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
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
}
