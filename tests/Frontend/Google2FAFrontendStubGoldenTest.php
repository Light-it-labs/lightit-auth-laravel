<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;
use Symfony\Component\Finder\Finder;

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
        ['routes/(public)/_guest/two-factor/setup/page.tsx.stub', 'src/routes/(public)/_guest/two-factor/setup/page.tsx'],
        ['routes/(public)/_guest/two-factor/-components/recovery-codes.tsx.stub', 'src/routes/(public)/_guest/two-factor/-components/recovery-codes.tsx'],
        ['routes/(public)/_guest/two-factor/page.tsx.stub', 'src/routes/(public)/_guest/two-factor/page.tsx'],
        ['routes/(public)/_guest/two-factor/recovery-code/page.tsx.stub', 'src/routes/(public)/_guest/two-factor/recovery-code/page.tsx'],
    ]);

    it('leaves no placeholder unresolved in any stub', function () use ($stubPath): void {
        $finder = (new Finder)->files()->in($stubPath(''))->name('*.stub');
        $renderer = new StubRenderer;

        expect($finder)->toHaveCount(9);

        foreach ($finder as $file) {
            expect($renderer->render($file->getPathname(), FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });

    it('routes the two adaptations from idr-front at the package\'s own paths, not idr-front\'s', function () use (
        $fixturePath, $stubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('services/auth/two-factor/api.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->toBe(file_get_contents($fixturePath('src/services/auth/two-factor/api.ts')))
            ->toContain('"2fa/verify-recovery-code"')
            ->toContain('"2fa/regenerate-recovery-codes"')
            ->not->toContain('auth/verify-recovery-code')
            ->not->toContain('auth/regenerate-recovery-codes');
    });

    it('attaches a manual Authorization header per call instead of a shared authenticated client', function () use (
        $fixturePath, $stubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('services/auth/two-factor/api.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->toBe(file_get_contents($fixturePath('src/services/auth/two-factor/api.ts')))
            ->toContain('Authorization: `Bearer ${token}`')
            ->not->toContain('withCredentials');
    });

    it('spells the provenance marker so cspell can tokenize it', function () use (
        $fixturePath, $stubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('AUTH-2FA-FRONTEND-TODO.md.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->toBe(file_get_contents($fixturePath('AUTH-2FA-FRONTEND-TODO.md')))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });
});

describe('Shared frontend stub rendering', function () use ($fixturePath): void {
    $sharedStubPath = static function (string $relative): string {
        return __DIR__.'/../../src/Stubs/Frontend/Shared/'.$relative;
    };

    it('renders the session seam stub byte-for-byte against its golden fixture', function () use (
        $fixturePath, $sharedStubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $sharedStubPath('services/auth/session.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)->toBe(file_get_contents($fixturePath('src/services/auth/session.ts')));
    });

    it('never exposes accessToken to callers - only persistSession/clearSession/getAccessToken', function () use (
        $sharedStubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $sharedStubPath('services/auth/session.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->toContain('export const persistSession')
            ->toContain('export const clearSession')
            ->toContain('export const getAccessToken')
            ->not->toContain('export const accessToken')
            ->not->toContain('export let accessToken');
    });
});
