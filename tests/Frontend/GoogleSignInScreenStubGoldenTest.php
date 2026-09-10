<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;
use Symfony\Component\Finder\Finder;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/GoogleSSO/'.$relative;
};

describe('Google sign-in button stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders each file byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['hooks/use-google-identity-services.ts.stub', 'src/hooks/use-google-identity-services.ts'],
        [
            'routes/(public)/_guest/login/-components/google-login-button.tsx.stub',
            'src/routes/(public)/_guest/login/-components/google-login-button.tsx',
        ],
    ]);

    it('never reads accessToken directly - the button forwards the whole result to persistSession', function () use ($stubPath): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('routes/(public)/_guest/login/-components/google-login-button.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->not->toContain('.accessToken')
            ->not->toContain('accessToken:')
            ->toContain('persistSession(result)');
    });

    it('loads the Identity Services script from its CDN URL, not an npm import', function () use ($stubPath): void {
        $rendered = (new StubRenderer)->render(
            $stubPath('hooks/use-google-identity-services.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($rendered)
            ->not->toContain('from "@react-oauth/google"')
            ->toContain('https://accounts.google.com/gsi/client');
    });

    it('never leaves a script-loading rejection unhandled - it surfaces an error state instead', function () use ($stubPath): void {
        $hook = (new StubRenderer)->render(
            $stubPath('hooks/use-google-identity-services.ts.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($hook)
            ->not->toContain('void loadGoogleIdentityServices()')
            ->toContain('.catch(')
            ->toContain('isError');

        $button = (new StubRenderer)->render(
            $stubPath('routes/(public)/_guest/login/-components/google-login-button.tsx.stub'),
            FrontendStubTokens::defaults(),
        );

        expect($button)->toContain('identityServicesFailed');
    });

    it('leaves no placeholder unresolved in any stub', function () use ($stubPath): void {
        $finder = (new Finder)->files()->in($stubPath(''))->name('*.stub');
        $renderer = new StubRenderer;

        expect($finder)->toHaveCount(7);

        foreach ($finder as $file) {
            expect($renderer->render($file->getPathname(), FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });
});
