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

    it('declares the constant after the last import', function () use ($queryClient): void {
        file_put_contents($this->path, $queryClient);

        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->path))
            ->toBe(TypeScriptPatchOutcome::Patched);

        expect(file_get_contents($this->path))
            ->toContain("import { env } from \"./env\";\n\nconst CSRF_TOKEN_MISMATCH = 419;");
    });

    it('adds the status to the retry list keeping the surrounding indentation', function () use ($queryClient): void {
        file_put_contents($this->path, $queryClient);

        (new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->path);

        expect(file_get_contents($this->path))
            ->toContain(
                "              HttpStatusCode.NotFound,\n              CSRF_TOKEN_MISMATCH,\n            ].includes"
            );
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

    it('survives the retry predicate being collapsed onto one line', function (): void {
        file_put_contents($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            const retry = (error) => [HttpStatusCode.Unauthorized, HttpStatusCode.NotFound].includes(error.response.status);

            TS);

        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->path))
            ->toBe(TypeScriptPatchOutcome::Patched);

        expect(file_get_contents($this->path))->toContain('CSRF_TOKEN_MISMATCH,');
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

    it('does not treat an unrelated 419 as an applied patch', function (): void {
        file_put_contents($this->path, <<<'TS'
            import { HttpStatusCode } from "axios";

            const staleTime = 419;
            const retry = (error) => [HttpStatusCode.Unauthorized].includes(error.response.status);

            TS);

        expect((new TypeScriptPatcher())->addCsrfMismatchToRetryList($this->path))
            ->toBe(TypeScriptPatchOutcome::Patched);

        expect(file_get_contents($this->path))->toContain('CSRF_TOKEN_MISMATCH,');
    });
});
