<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeGoogleSSOFrontendCommand;

describe('GoogleSSOFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-google-sso-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes every Google SSO service file and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')->assertSuccessful();

        foreach ([
            'src/services/auth/sso/google/types.ts',
            'src/services/auth/sso/google/schemas.ts',
            'src/services/auth/sso/google/api.ts',
            'src/services/auth/sso/google/actions.ts',
            'AUTH-GOOGLE-SSO-FRONTEND-TODO.md',
        ] as $relative) {
            expect(file_get_contents($this->root.'/'.$relative))
                ->toBe(file_get_contents(__DIR__.'/../Fixtures/frontend/expected/'.$relative));
        }
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-GOOGLE-SSO-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand);

        $this->artisan('google-sso-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
