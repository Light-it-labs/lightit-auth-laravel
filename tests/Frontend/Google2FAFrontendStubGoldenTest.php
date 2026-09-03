<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Google2FA/'.$relative;
};

describe('Google2FA frontend stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders a stub byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['services/auth/two-factor/types.ts.stub', 'src/services/auth/two-factor/types.ts'],
        ['services/auth/two-factor/api.ts.stub', 'src/services/auth/two-factor/api.ts'],
        ['services/auth/two-factor/actions.ts.stub', 'src/services/auth/two-factor/actions.ts'],
        ['services/auth/two-factor/schemas.ts.stub', 'src/services/auth/two-factor/schemas.ts'],
        ['AUTH-2FA-FRONTEND-TODO.md.stub', 'AUTH-2FA-FRONTEND-TODO.md'],
    ]);

    it('leaves no placeholder unresolved in any stub', function () use ($stubPath): void {
        $stubs = glob($stubPath('{,*/,*/*/,*/*/*/}*.stub'), \GLOB_BRACE);
        $renderer = new StubRenderer;

        expect($stubs)->toHaveCount(5);

        foreach ($stubs as $stub) {
            expect($renderer->render($stub, FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });

    it('routes the two adaptations from idr-front at the package\'s own paths, not idr-front\'s', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('src/services/auth/two-factor/api.ts')))
            ->toContain('"2fa/verify-recovery-code"')
            ->toContain('"2fa/regenerate-recovery-codes"')
            ->not->toContain('auth/verify-recovery-code')
            ->not->toContain('auth/regenerate-recovery-codes');
    });

    it('attaches a manual Authorization header per call instead of a shared authenticated client', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('src/services/auth/two-factor/api.ts')))
            ->toContain('Authorization: `Bearer ${token}`')
            ->not->toContain('withCredentials');
    });

    it('spells the provenance marker so cspell can tokenize it', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('AUTH-2FA-FRONTEND-TODO.md')))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });
});
