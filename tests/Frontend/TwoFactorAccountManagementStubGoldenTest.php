<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/src/routes/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Google2FA/routes/'.$relative;
};

describe('two-factor account management stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders each screen byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        [
            '_private/security/-components/disable-two-factor-dialog.tsx.stub',
            '_private/security/-components/disable-two-factor-dialog.tsx',
        ],
        [
            '_private/security/-components/request-two-factor-reset-dialog.tsx.stub',
            '_private/security/-components/request-two-factor-reset-dialog.tsx',
        ],
        [
            '_private/security/-components/regenerate-recovery-codes-dialog.tsx.stub',
            '_private/security/-components/regenerate-recovery-codes-dialog.tsx',
        ],
        [
            '_private/security/-components/two-factor-section.tsx.stub',
            '_private/security/-components/two-factor-section.tsx',
        ],
        [
            '(public)/_guest/two-factor/reset/page.tsx.stub',
            '(public)/_guest/two-factor/reset/page.tsx',
        ],
    ]);

    it('never completes an authentication - none of these call persistSession', function () use ($stubPath): void {
        $screens = [
            '_private/security/-components/disable-two-factor-dialog.tsx.stub',
            '_private/security/-components/request-two-factor-reset-dialog.tsx.stub',
            '_private/security/-components/regenerate-recovery-codes-dialog.tsx.stub',
            '_private/security/-components/two-factor-section.tsx.stub',
            '(public)/_guest/two-factor/reset/page.tsx.stub',
        ];

        foreach ($screens as $stub) {
            $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

            expect($rendered)->not->toContain('persistSession');
        }
    });

    it('never reads the real session accessToken directly - only through a token prop', function () use ($stubPath): void {
        // The reset-request dialog is the one exception: `data.accessToken` there is the
        // reset endpoint's own short-lived challenge token from the mutation response,
        // not the session's accessToken from services/auth/session - it never touches
        // session.ts, matching the "sends the reset challenge token through the URL" test.
        $screens = [
            '_private/security/-components/disable-two-factor-dialog.tsx.stub',
            '_private/security/-components/regenerate-recovery-codes-dialog.tsx.stub',
        ];

        foreach ($screens as $stub) {
            $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

            expect($rendered)
                ->not->toContain('.accessToken')
                ->not->toContain('getAccessToken');
        }
    });

    it('sends the reset challenge token through the URL, not through session.ts', function () use ($stubPath): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('_private/security/-components/request-two-factor-reset-dialog.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->toContain('to: "/two-factor/reset"')
            ->toContain('search: { token: data.accessToken }');
    });

    it('reuses the setup flow\'s recovery-codes display instead of duplicating it', function () use ($stubPath): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('_private/security/-components/regenerate-recovery-codes-dialog.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)->toContain('from "../../../(public)/_guest/two-factor/-components/recovery-codes"');
    });

    it('composes into the security host page alongside the passkeys section', function () use ($fixturePath): void {
        expect(file_get_contents($fixturePath('_private/security/page.tsx')))
            ->toContain('TwoFactorSection')
            ->toContain('PasskeysSection');
    });

    it('leaves no placeholder unresolved in any screen stub', function () use ($stubPath): void {
        $stubs = [
            '_private/security/-components/disable-two-factor-dialog.tsx.stub',
            '_private/security/-components/request-two-factor-reset-dialog.tsx.stub',
            '_private/security/-components/regenerate-recovery-codes-dialog.tsx.stub',
            '_private/security/-components/two-factor-section.tsx.stub',
            '(public)/_guest/two-factor/reset/page.tsx.stub',
        ];
        $renderer = new StubRenderer;

        foreach ($stubs as $stub) {
            expect($renderer->render($stubPath($stub), FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });
});
