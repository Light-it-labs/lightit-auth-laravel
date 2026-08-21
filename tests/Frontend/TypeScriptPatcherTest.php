<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\TypeScriptPatcher;
use Lightitlabs\Auth\Frontend\TypeScriptPatchOutcome;

describe('TypeScriptPatcher', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir() . '/ts-patcher-' . uniqid();
        mkdir($this->directory, 0755, true);
        $this->path = $this->directory . '/query-client.ts';
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
    });

    $patch = static function (string $path, string $contents): string {
        file_put_contents($path, $contents);

        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($path))
            ->toBe(TypeScriptPatchOutcome::Patched);

        return (string) file_get_contents($path);
    };

    $queryClient = <<<'TS'
        import * as Sentry from "@sentry/react";
        import { QueryClient } from "@tanstack/react-query";
        import { HttpStatusCode, isAxiosError } from "axios";
        import { ZodError } from "zod";

        import { env } from "./env";

        export const queryClient = new QueryClient({
          defaultOptions: {
            queries: {
              retry: (failureCount, error) => {
                return error instanceof ZodError ||
                  (isAxiosError(error) &&
                    error.response?.status &&
                    [
                      HttpStatusCode.Unauthorized,
                      HttpStatusCode.Forbidden,
                      HttpStatusCode.NotFound,
                    ].includes(error.response.status))
                  ? false
                  : failureCount <= 3;
              },
            },
          },
        });

        TS;

    it('patches a multiline list whose last entry carries a trailing comma', function () use (
        $patch,
        $queryClient
    ): void {
        expect($patch($this->path, $queryClient))->toBe(<<<'TS'
            import * as Sentry from "@sentry/react";
            import { QueryClient } from "@tanstack/react-query";
            import { HttpStatusCode, isAxiosError } from "axios";
            import { ZodError } from "zod";

            import { env } from "./env";

            const CSRF_TOKEN_MISMATCH = 419;

            export const queryClient = new QueryClient({
              defaultOptions: {
                queries: {
                  retry: (failureCount, error) => {
                    return error instanceof ZodError ||
                      (isAxiosError(error) &&
                        error.response?.status &&
                        [
                          HttpStatusCode.Unauthorized,
                          HttpStatusCode.Forbidden,
                          HttpStatusCode.NotFound,
                          CSRF_TOKEN_MISMATCH,
                        ].includes(error.response.status))
                      ? false
                      : failureCount <= 3;
                  },
                },
              },
            });

            TS);
    });

    it('patches a multiline list whose last entry has no trailing comma', function () use (
        $patch
    ): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound
              ].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound,
                CSRF_TOKEN_MISMATCH,
              ].includes(error.response.status);

            TS);
    });

    it('patches a collapsed one-line list whose last entry has no trailing comma', function () use (
        $patch
    ): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            export const retry = (error) => [HttpStatusCode.Unauthorized, HttpStatusCode.NotFound].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            export const retry = (error) => [HttpStatusCode.Unauthorized, HttpStatusCode.NotFound, CSRF_TOKEN_MISMATCH].includes(error.response.status);

            TS);
    });

    it('patches a collapsed one-line list whose last entry carries a trailing comma', function () use (
        $patch
    ): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            export const retry = (error) => [HttpStatusCode.Unauthorized, HttpStatusCode.NotFound,].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            export const retry = (error) => [HttpStatusCode.Unauthorized, HttpStatusCode.NotFound, CSRF_TOKEN_MISMATCH].includes(error.response.status);

            TS);
    });

    it('keeps a trailing line comment that already sits behind a comma', function () use (
        $patch
    ): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound, // the resource is gone for good
              ].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound, // the resource is gone for good
                CSRF_TOKEN_MISMATCH,
              ].includes(error.response.status);

            TS);
    });

    it('puts the separating comma before a trailing comment, not inside it', function () use (
        $patch
    ): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound // the resource is gone for good
              ].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            export const retry = (error) =>
              [
                HttpStatusCode.Unauthorized,
                HttpStatusCode.NotFound, // the resource is gone for good
                CSRF_TOKEN_MISMATCH,
              ].includes(error.response.status);

            TS);
    });

    it('leaves an already patched file untouched', function () use ($queryClient): void {
        file_put_contents($this->path, $queryClient);
        $patcher = new TypeScriptPatcher();
        $patcher->addCsrfMismatchToRetryList($this->path);
        $patched = file_get_contents($this->path);

        expect($patcher->addCsrfMismatchToRetryList($this->path))
            ->toBe(TypeScriptPatchOutcome::AlreadyApplied);

        expect(file_get_contents($this->path))->toBe($patched);
    });

    it('reports the anchor as missing and writes nothing when the retry list is absent', function (): void {
        $withoutRetryList = "import { QueryClient } from \"@tanstack/react-query\";\n\nexport const queryClient = new QueryClient({});\n";
        file_put_contents($this->path, $withoutRetryList);

        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->path))
            ->toBe(TypeScriptPatchOutcome::AnchorNotFound);

        expect(file_get_contents($this->path))->toBe($withoutRetryList);
    });

    it('reports a missing file', function (): void {
        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->directory . '/absent.ts'))
            ->toBe(TypeScriptPatchOutcome::Missing);
    });

    it('needs a manual step for every outcome that did not land the change', function (): void {
        expect(TypeScriptPatchOutcome::AnchorNotFound->needsManualStep())->toBeTrue()
            ->and(TypeScriptPatchOutcome::Failed->needsManualStep())->toBeTrue()
            ->and(TypeScriptPatchOutcome::Corrupted->needsManualStep())->toBeTrue()
            ->and(TypeScriptPatchOutcome::Missing->needsManualStep())->toBeTrue()
            ->and(TypeScriptPatchOutcome::Patched->needsManualStep())->toBeFalse()
            ->and(TypeScriptPatchOutcome::AlreadyApplied->needsManualStep())->toBeFalse();
    });

    it('does not treat an unrelated 419 as an applied patch', function () use ($patch): void {
        expect($patch($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            const staleTime = 419;
            export const retry = (error) => [HttpStatusCode.Unauthorized].includes(error.response.status);

            TS))->toBe(<<<'TS'
            import { HttpStatusCode } from "axios";

            const CSRF_TOKEN_MISMATCH = 419;

            const staleTime = 419;
            export const retry = (error) => [HttpStatusCode.Unauthorized, CSRF_TOKEN_MISMATCH].includes(error.response.status);

            TS);
    });
});
