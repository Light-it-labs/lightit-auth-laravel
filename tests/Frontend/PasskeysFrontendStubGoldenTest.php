<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Tools\StubRenderer;

$fixturePath = static function (string $relative): string {
    return __DIR__.'/../Fixtures/frontend/expected/'.$relative;
};

$stubPath = static function (string $relative): string {
    return __DIR__.'/../../src/Stubs/Frontend/Passkeys/'.$relative;
};

describe('Passkeys frontend stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders a stub byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer)->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['services/auth/passkeys/types.ts.stub', 'src/services/auth/passkeys/types.ts'],
        ['services/auth/passkeys/schemas.ts.stub', 'src/services/auth/passkeys/schemas.ts'],
        ['services/auth/passkeys/api.ts.stub', 'src/services/auth/passkeys/api.ts'],
        ['services/auth/passkeys/actions.ts.stub', 'src/services/auth/passkeys/actions.ts'],
        ['AUTH-PASSKEYS-FRONTEND-TODO.md.stub', 'AUTH-PASSKEYS-FRONTEND-TODO.md'],
    ]);

    it('leaves no placeholder unresolved in any stub', function () use ($stubPath): void {
        $stubs = [
            ...glob($stubPath('*.stub')),
            ...glob($stubPath('services/auth/passkeys/*.stub')),
        ];
        $renderer = new StubRenderer;

        expect($stubs)->toHaveCount(5);

        foreach ($stubs as $stub) {
            expect($renderer->render($stub, FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });

    it('never routes the WebAuthn options payloads through the camelCase interceptor', function () use (
        $fixturePath
    ): void {
        $api = file_get_contents($fixturePath('src/services/auth/passkeys/api.ts'));

        expect($api)
            ->toContain('passkeysCeremonyApi')
            ->toContain('no response interceptor at all');

        $start = strpos($api, 'const passkeysCeremonyApi');
        $end = strpos($api, 'const bearer');

        // A silently-false strpos() here would collapse the slice below to an
        // empty string, making the assertion vacuously true instead of failing.
        expect($start)->not->toBeFalse();
        expect($end)->not->toBeFalse();

        $ceremonyBlock = substr($api, $start, $end - $start);

        expect($ceremonyBlock)->not->toContain('deepCamelKeys');
    });

    it('attaches a manual Authorization header per call instead of a shared authenticated client', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('src/services/auth/passkeys/api.ts')))
            ->toContain('Authorization: `Bearer ${token}`')
            ->not->toContain('withCredentials');
    });

    it('spells the provenance marker so cspell can tokenize it', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('AUTH-PASSKEYS-FRONTEND-TODO.md')))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });
});
