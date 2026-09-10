<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeSharedFrontendSeamCommand;

describe('SharedFrontendSeamInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-shared-seam-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes nothing when no seam is needed', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(false, false, false, $this->root));

        $this->artisan('shared-frontend-seam-fake')->assertSuccessful();

        expect($this->root.'/src/services/auth/session.ts')->not->toBeFile();
        expect($this->root.'/src/routes/_private/security/page.tsx')->not->toBeFile();
    });

    it('writes only the session seam when only that is needed', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(true, false, false, $this->root));

        $this->artisan('shared-frontend-seam-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/services/auth/session.ts'))
            ->toBe(file_get_contents(__DIR__.'/../Fixtures/frontend/expected/src/services/auth/session.ts'));
        expect($this->root.'/src/routes/_private/security/page.tsx')->not->toBeFile();
    });

    it('writes the passkeys-only security page when only passkeys needs the host', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(false, true, false, $this->root));

        $this->artisan('shared-frontend-seam-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/routes/_private/security/page.tsx'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/routes/_private/security/page.passkeys-only.tsx'
            ))
            ->toContain('PasskeysSection')
            ->not->toContain('TwoFactorSection');
    });

    it('writes the two-factor-only security page when only 2FA account management needs the host', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(false, false, true, $this->root));

        $this->artisan('shared-frontend-seam-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/routes/_private/security/page.tsx'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/routes/_private/security/page.two-factor-only.tsx'
            ))
            ->toContain('TwoFactorSection')
            ->not->toContain('PasskeysSection');
    });

    it('writes the combined security page when both passkeys and 2FA account management are selected', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(true, true, true, $this->root));

        $this->artisan('shared-frontend-seam-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/routes/_private/security/page.tsx'))
            ->toBe(file_get_contents(__DIR__.'/../Fixtures/frontend/expected/src/routes/_private/security/page.tsx'))
            ->toContain('TwoFactorSection')
            ->toContain('PasskeysSection');
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeSharedFrontendSeamCommand(true, true, true));

        $this->artisan('shared-frontend-seam-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
