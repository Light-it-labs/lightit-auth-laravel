<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;
use Symfony\Component\Finder\Finder;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/src/routes/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Passkeys/routes/'.$relative;
};

describe('passkeys screen stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders each screen byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        [
            '(public)/_guest/login/-components/passkey-login-button.tsx.stub',
            '(public)/_guest/login/-components/passkey-login-button.tsx',
        ],
        [
            '_private/security/-components/passkeys-section.tsx.stub',
            '_private/security/-components/passkeys-section.tsx',
        ],
        [
            '_private/security/-components/enrol-passkey-dialog.tsx.stub',
            '_private/security/-components/enrol-passkey-dialog.tsx',
        ],
    ]);

    it('never reads accessToken directly - the sign-in button goes through persistSession', function () use (
        $stubPath
    ): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('(public)/_guest/login/-components/passkey-login-button.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->not->toContain('.accessToken')
            ->not->toContain('accessToken:')
            ->toContain('persistSession(result)');
    });

    it('gates the login button and management section on isPasskeySupported, differently', function () use (
        $stubPath
    ): void {
        $button = (new StubRenderer)->render(
            $stubPath('(public)/_guest/login/-components/passkey-login-button.tsx.stub'),
            FrontendStubTokens::defaults(),
        );
        $section = (new StubRenderer)->render(
            $stubPath('_private/security/-components/passkeys-section.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        // The button disappears silently when unsupported; the management
        // section instead renders an explicit message, per the reference
        // app's own split (see AUTH-PASSKEYS-FRONTEND-TODO.md).
        expect($button)
            ->toContain('isPasskeySupported()')
            ->toContain('return null;');

        expect($section)
            ->toContain('isPasskeySupported()')
            ->toContain('can&apos;t use passkeys');
    });

    it('leaves no placeholder unresolved in any screen stub', function () use ($stubPath): void {
        $finder = (new Finder)->files()->in($stubPath(''))->name('*.stub');
        $renderer = new StubRenderer;

        expect($finder)->toHaveCount(3);

        foreach ($finder as $file) {
            expect($renderer->render($file->getPathname(), FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });
});
