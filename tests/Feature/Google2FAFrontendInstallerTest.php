<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FAFrontendCommand;

describe('Google2FAFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-2fa-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes every 2FA service file and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ([
            'src/services/auth/two-factor/types.ts',
            'src/services/auth/two-factor/schemas.ts',
            'src/services/auth/two-factor/api.ts',
            'src/services/auth/two-factor/actions.ts',
            'AUTH-2FA-FRONTEND-TODO.md',
        ] as $relative) {
            expect($this->root.'/'.$relative)->toBeFile();
        }

        expect(file_get_contents($this->root.'/src/services/auth/two-factor/api.ts'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/services/auth/two-factor/api.ts'
            ));
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand);

        $this->artisan('google2fa-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
