<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/src/routes/_private/security/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Shared/routes/_private/security/'.$relative;
};

describe('security page host stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders each host variant byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['page.passkeys-only.tsx.stub', 'page.passkeys-only.tsx'],
        ['page.two-factor-only.tsx.stub', 'page.two-factor-only.tsx'],
        ['page.both.tsx.stub', 'page.tsx'],
    ]);

    it('never emits an import a single-feature variant does not use', function () use ($stubPath): void {
        $passkeysOnly = (new StubRenderer)->render(
            $stubPath('page.passkeys-only.tsx.stub'),
            FrontendStubTokens::defaults(),
        );
        $twoFactorOnly = (new StubRenderer)->render(
            $stubPath('page.two-factor-only.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($passkeysOnly)
            ->toContain('PasskeysSection')
            ->not->toContain('TwoFactorSection');

        expect($twoFactorOnly)
            ->toContain('TwoFactorSection')
            ->not->toContain('PasskeysSection');
    });
});
