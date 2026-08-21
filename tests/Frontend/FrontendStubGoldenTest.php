<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Auth\Installers\SanctumCookieFrontendInstaller;
use Lightitlabs\Tools\StubRenderer;

$fixturePath = static function (string $relative): string {
    return __DIR__ . '/../Fixtures/frontend/expected/' . $relative;
};

$stubPath = static function (string $relative): string {
    return SanctumCookieFrontendInstaller::stubDirectory() . '/' . $relative;
};

describe('frontend stub rendering', function () use ($fixturePath, $stubPath): void {
    it('renders a stub byte-for-byte against its golden fixture', function (
        string $stub,
        string $fixture,
    ) use ($fixturePath, $stubPath): void {
        $rendered = (new StubRenderer())->render($stubPath($stub), FrontendStubTokens::defaults());

        expect($rendered)->toBe(file_get_contents($fixturePath($fixture)));
    })->with([
        ['config/api.ts.stub', 'src/config/api.ts'],
        ['services/auth/api.ts.stub', 'src/services/auth/api.ts'],
        ['services/auth/factories.ts.stub', 'src/services/auth/factories.ts'],
        ['services/auth/actions.ts.stub', 'src/services/auth/actions.ts'],
        ['services/auth/types.ts.stub', 'src/services/auth/types.ts'],
        ['services/auth/schemas.zod4.ts.stub', 'src/services/auth/schemas.ts'],
        ['services/auth/schemas.zod3.ts.stub', 'src/services/auth/schemas.zod3.ts'],
        ['AUTH-FRONTEND-TODO.md.stub', 'AUTH-FRONTEND-TODO.md'],
    ]);

    it('leaves no placeholder unresolved in any stub', function () use ($stubPath): void {
        $stubs = glob($stubPath('{,*/,*/*/}*.stub'), \GLOB_BRACE);
        $renderer = new StubRenderer();

        expect($stubs)->toHaveCount(8);

        foreach ($stubs as $stub) {
            expect($renderer->render($stub, FrontendStubTokens::defaults()))
                ->not->toMatch('/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/');
        }
    });

    it('only ships the zod 4 variant with z.email()', function () use ($stubPath): void {
        expect(file_get_contents($stubPath('services/auth/schemas.zod4.ts.stub')))
            ->toContain('email: z.email({')
            ->and(file_get_contents($stubPath('services/auth/schemas.zod3.ts.stub')))
            ->toContain('email: z.string().email({')
            ->not->toContain('email: z.email(');
    });

    it('emits the axios cookie flags the backend contract depends on', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('src/config/api.ts')))
            ->toContain('withCredentials: true')
            ->toContain('withXSRFToken: true')
            ->toContain('xsrfCookieName: "XSRF-TOKEN"')
            ->toContain('xsrfHeaderName: "X-XSRF-TOKEN"')
            ->toContain('"X-Requested-With": "XMLHttpRequest"')
            ->toContain('Accept: "application/json"')
            ->toContain('env.VITE_API_URL.replace(/\/api$/, "")');
    });

    it('spells the provenance marker so cspell can tokenize it', function () use (
        $fixturePath
    ): void {
        expect(file_get_contents($fixturePath('AUTH-FRONTEND-TODO.md')))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });
});
