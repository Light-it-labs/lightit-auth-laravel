<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakePasskeysFrontendCommand;

describe('PasskeysFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-passkeys-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes every passkeys service file and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakePasskeysFrontendCommand($this->root));

        $this->artisan('passkeys-frontend-fake')->assertSuccessful();

        foreach ([
            'src/services/auth/passkeys/types.ts',
            'src/services/auth/passkeys/schemas.ts',
            'src/services/auth/passkeys/api.ts',
            'src/services/auth/passkeys/actions.ts',
            'AUTH-PASSKEYS-FRONTEND-TODO.md',
        ] as $relative) {
            expect($this->root.'/'.$relative)->toBeFile();
        }

        expect(file_get_contents($this->root.'/src/services/auth/passkeys/api.ts'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/services/auth/passkeys/api.ts'
            ));
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakePasskeysFrontendCommand($this->root));

        $this->artisan('passkeys-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-PASSKEYS-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakePasskeysFrontendCommand);

        $this->artisan('passkeys-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
