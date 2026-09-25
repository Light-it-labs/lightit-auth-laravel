<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FAFrontendCommand;

describe('Google2FAFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-2fa-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);

        $this->writtenFiles = [
            'src/services/auth/two-factor/types.ts',
            'src/services/auth/two-factor/schemas.ts',
            'src/services/auth/two-factor/api.ts',
            'src/services/auth/two-factor/actions.ts',
            'AUTH-2FA-FRONTEND-TODO.md',
        ];
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes exactly the 2FA service files and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(file_exists($this->root.'/'.$relative))->toBeTrue();
        }
    });

    it('leaves no placeholder unresolved in any written file', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(file_get_contents($this->root.'/'.$relative))
                ->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
        }
    });

    it('renders api.ts with the package\'s own endpoint names, not idr-front\'s', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/services/auth/two-factor/api.ts'))
            ->toContain('"2fa/setup"')
            ->toContain('"2fa/complete"')
            ->toContain('"2fa/verify-recovery-code"')
            ->toContain('"2fa/regenerate-recovery-codes"')
            ->not->toContain('auth/verify-recovery-code')
            ->not->toContain('auth/regenerate-recovery-codes');
    });

    it('attaches a manual Authorization header per call instead of a shared authenticated client', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/src/services/auth/two-factor/api.ts'))
            ->toContain('Authorization: `Bearer ${token}`')
            ->not->toContain('withCredentials');
    });

    it('never emits the removed Bearer-login contract', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(file_get_contents($this->root.'/'.$relative))
                ->not->toContain('Bearer"')
                ->not->toContain('BearerTokenResult')
                ->not->toContain('persistSession');
        }
    });

    it('spells the provenance marker so cspell can tokenize it', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });

    it('tells the reader which i18n keys the generated schemas need', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('form.otp')
            ->toContain('form.recoveryCode');
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('reports Skipped instead of Overwriting on a second run, and leaves every file byte-identical', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));
        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        $filesAfterFirstRun = [];
        foreach ($this->writtenFiles as $relative) {
            $filesAfterFirstRun[$relative] = file_get_contents($this->root.'/'.$relative);
        }

        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $command = $this->artisan('google2fa-frontend-fake');

        foreach ($this->writtenFiles as $relative) {
            $command->expectsOutputToContain('Skipped '.$relative);
        }

        $command->doesntExpectOutputToContain('Overwriting')->assertSuccessful();

        foreach ($filesAfterFirstRun as $relative => $contentsAfterFirstRun) {
            expect(file_get_contents($this->root.'/'.$relative))->toBe($contentsAfterFirstRun);
        }
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand);

        $this->artisan('google2fa-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
