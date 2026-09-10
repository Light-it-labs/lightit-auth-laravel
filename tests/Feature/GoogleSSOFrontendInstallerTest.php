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

    it('writes every Google SSO service file, the sign-in button, and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')->assertSuccessful();

        foreach ([
            'src/services/auth/sso/google/types.ts',
            'src/services/auth/sso/google/schemas.ts',
            'src/services/auth/sso/google/api.ts',
            'src/services/auth/sso/google/actions.ts',
            'src/hooks/use-google-identity-services.ts',
            'AUTH-GOOGLE-SSO-FRONTEND-TODO.md',
        ] as $relative) {
            expect(file_get_contents($this->root.'/'.$relative))
                ->toBe(file_get_contents(__DIR__.'/../Fixtures/frontend/expected/'.$relative));
        }

        expect(file_get_contents($this->root.'/src/routes/(public)/_guest/login/-components/google-login-button.tsx'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/routes/(public)/_guest/login/-components/google-login-button.tsx'
            ));
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/AUTH-GOOGLE-SSO-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('patches src/config/env.ts with VITE_GOOGLE_CLIENT_ID', function (): void {
        $envPath = $this->root.'/src/config/env.ts';
        mkdir(dirname($envPath), 0755, true);
        file_put_contents($envPath, <<<'TS'
            import { createEnv } from "@t3-oss/env-core";
            import { z } from "zod";

            export const env = createEnv({
              clientPrefix: "VITE_",

              client: {
                VITE_APP_NAME: z.string().min(1),
                VITE_API_URL: z.string().min(1),
              },

              runtimeEnv: import.meta.env,

              emptyStringAsUndefined: true,
            });

            TS);

        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')
            ->expectsOutputToContain('Patched: src/config/env.ts')
            ->assertSuccessful();

        expect(file_get_contents($envPath))->toBe(<<<'TS'
            import { createEnv } from "@t3-oss/env-core";
            import { z } from "zod";

            export const env = createEnv({
              clientPrefix: "VITE_",

              client: {
                VITE_APP_NAME: z.string().min(1),
                VITE_API_URL: z.string().min(1),
                VITE_GOOGLE_CLIENT_ID: z.string().min(1),
              },

              runtimeEnv: import.meta.env,

              emptyStringAsUndefined: true,
            });

            TS);
    });

    it('warns and reports the manual step when env.ts does not exist', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand($this->root));

        $this->artisan('google-sso-frontend-fake')
            ->expectsOutputToContain('Could not add VITE_GOOGLE_CLIENT_ID to src/config/env.ts automatically')
            ->assertSuccessful();

        expect(file_exists($this->root.'/src/config/env.ts'))->toBeFalse();
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeGoogleSSOFrontendCommand);

        $this->artisan('google-sso-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });
});
