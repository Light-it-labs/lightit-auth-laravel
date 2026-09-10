<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;
use Symfony\Component\Finder\Finder;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/src/routes/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Google2FA/routes/'.$relative;
};

describe('two-factor screen stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders each screen byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['(public)/_guest/two-factor/setup/page.tsx.stub', '(public)/_guest/two-factor/setup/page.tsx'],
        ['(public)/_guest/two-factor/-components/recovery-codes.tsx.stub', '(public)/_guest/two-factor/-components/recovery-codes.tsx'],
        ['(public)/_guest/two-factor/page.tsx.stub', '(public)/_guest/two-factor/page.tsx'],
        ['(public)/_guest/two-factor/recovery-code/page.tsx.stub', '(public)/_guest/two-factor/recovery-code/page.tsx'],
    ]);

    it('never reads accessToken directly - every completed challenge goes through persistSession', function () use ($stubPath): void {
        $screensThatCompleteAuthentication = [
            '(public)/_guest/two-factor/page.tsx.stub',
            '(public)/_guest/two-factor/recovery-code/page.tsx.stub',
        ];

        foreach ($screensThatCompleteAuthentication as $stub) {
            $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

            expect($rendered)
                ->not->toContain('.accessToken')
                ->not->toContain('accessToken:')
                ->toContain('persistSession(result)');
        }
    });

    it('leaves no placeholder unresolved in any screen stub', function () use ($stubPath): void {
        $finder = (new Finder)->files()->in($stubPath(''))->name('*.stub');
        $renderer = new StubRenderer;

        expect($finder)->toHaveCount(9);

        foreach ($finder as $file) {
            expect($renderer->render($file->getPathname(), FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });
});
