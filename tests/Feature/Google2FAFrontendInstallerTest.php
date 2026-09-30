<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FAFrontendCommand;

describe('Google2FAFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/lightit-2fa-frontend-' . bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__ . '/../Fixtures/frontend/react-project', $this->root);

        $this->screenFiles = [
            'src/stores/use-two-factor-challenge-store.ts',
            'src/routes/(public)/_guest/login/-hooks/use-two-factor-login.ts',
            'src/routes/(public)/_guest/two-factor/-hooks/use-two-factor-completion.ts',
            'src/routes/(public)/_guest/two-factor/-components/one-time-password-form.tsx',
            'src/routes/(public)/_guest/two-factor/-components/recovery-code-form.tsx',
            'src/routes/(public)/_guest/two-factor/-components/recovery-codes.tsx',
            'src/routes/(public)/_guest/two-factor/page.tsx',
            'src/routes/(public)/_guest/two-factor/setup/page.tsx',
        ];

        $this->writtenFiles = [
            'src/services/auth/two-factor/types.ts',
            'src/services/auth/two-factor/schemas.ts',
            'src/services/auth/two-factor/api.ts',
            'src/services/auth/two-factor/actions.ts',
            ...$this->screenFiles,
            'AUTH-2FA-FRONTEND-TODO.md',
        ];
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it(
        'writes the 2FA services, the login screens and the TODO doc into the resolved frontend root',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

            $this->artisan('google2fa-frontend-fake')->assertSuccessful();

            foreach ($this->writtenFiles as $relative) {
                expect(file_exists($this->root . '/' . $relative))->toBeTrue();
            }
        }
    );

    it('leaves no placeholder unresolved in any written file', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(file_get_contents($this->root . '/' . $relative))
                ->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
        }
    });

    it('renders api.ts with the package\'s own /2fa/* endpoint names, not the /auth/* alternative', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/src/services/auth/two-factor/api.ts'))
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

        expect(file_get_contents($this->root . '/src/services/auth/two-factor/api.ts'))
            ->toContain('Authorization: `Bearer ${token}`')
            ->toContain('isAuthProbe: true')
            ->not->toContain('withCredentials');
    });

    it(
        'posts the challenge-aware login to the configured login endpoint after fetching the CSRF cookie',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

            $this->artisan('google2fa-frontend-fake')->assertSuccessful();

            expect(file_get_contents($this->root . '/src/services/auth/two-factor/api.ts'))
                ->toContain('await ensureCsrf();')
                ->toContain('api.post("auth/login", { email_address: emailAddress, password })');
        }
    );

    it(
        'keeps the setup secret stable and short-lived: one fetch per token, outside the auth keys, dropped on completion',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

            $this->artisan('google2fa-frontend-fake')->assertSuccessful();

            expect(file_get_contents($this->root . '/src/services/auth/two-factor/actions.ts'))
                ->toContain('const TWO_FACTOR_SETUP_QUERY_KEY = "twoFactorSetup";')
                ->toContain('queryKey: [TWO_FACTOR_SETUP_QUERY_KEY, token]')
                ->not->toContain('"auth", "twoFactorSetup"')
                ->toContain('staleTime: Infinity')
                ->toContain('gcTime: 0')
                ->toContain('retry: false')
                ->not->toContain('useSetupTwoFactor');

            expect(file_get_contents(
                $this->root . '/src/routes/(public)/_guest/two-factor/-hooks/use-two-factor-completion.ts'
            ))->toContain('removeTwoFactorSetupQueries();');
        }
    );

    it('keeps the challenge token out of the URL and out of persisted storage', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->screenFiles as $relative) {
            expect(file_get_contents($this->root . '/' . $relative))
                ->not->toContain('validateSearch')
                ->not->toMatch('/search[:=]\s*\{+[^}]*token/')
                ->not->toContain('localStorage')
                ->not->toContain('sessionStorage');
        }

        expect(file_get_contents($this->root . '/src/stores/use-two-factor-challenge-store.ts'))
            ->not->toContain('zustand/middleware')
            ->not->toContain('persist(');
    });

    it('writes no comments into the generated TypeScript', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            if (! preg_match('/\.tsx?$/', $relative)) {
                continue;
            }

            expect(file_get_contents($this->root . '/' . $relative))
                ->not->toMatch('#^\s*//#m')
                ->not->toContain('/*')
                ->not->toContain('eslint-disable');
        }
    });

    it('offers the recovery-code switch on the verification screen only, never on setup', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/src/routes/(public)/_guest/two-factor/page.tsx'))
            ->toContain('RecoveryCodeForm')
            ->toContain('getPendingTwoFactorChallenge("verification_required")');

        expect(file_get_contents($this->root . '/src/routes/(public)/_guest/two-factor/setup/page.tsx'))
            ->not->toContain('RecoveryCodeForm')
            ->toContain('getPendingTwoFactorChallenge("setup_required")');
    });

    it('prints the login-form manual step with the exact lines to swap', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')
            ->expectsOutputToContain('In src/routes/(public)/_guest/login/-components/login-form.tsx:')
            ->expectsOutputToContain('import { useLogin } from "@/services/auth/actions";')
            ->expectsOutputToContain('import { useTwoFactorLogin } from "../-hooks/use-two-factor-login";')
            ->expectsOutputToContain('const loginMutation = useTwoFactorLogin();')
            ->assertSuccessful();
    });

    it('prints only login-form lines that the TODO also tells the reader to paste', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        Artisan::call('google2fa-frontend-fake');

        preg_match_all(
            '/import \{ \w+ \} from "[^"]+";|const loginMutation = \w+\(\);/',
            Artisan::output(),
            $printedLines
        );

        expect($printedLines[0])->toHaveCount(4);

        $todo = (string) file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md');

        foreach ($printedLines[0] as $printedLine) {
            expect($todo)->toContain($printedLine);
        }
    });

    it('documents every i18n key the generated files add in the TODO', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        preg_match_all(
            '/```json\n(.*?)```/s',
            (string) file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md'),
            $blocks
        );
        $documented = [
            'form' => json_decode('{' . $blocks[1][0] . '}', true, flags: JSON_THROW_ON_ERROR),
            ...json_decode('{' . $blocks[1][1] . '}', true, flags: JSON_THROW_ON_ERROR),
        ];

        $usedKeys = [];

        foreach ($this->writtenFiles as $relative) {
            preg_match_all(
                '/\bt\("(twoFactor\.[a-zA-Z.]+|form\.otp|form\.recoveryCode)"\)/',
                (string) file_get_contents($this->root . '/' . $relative),
                $matches
            );
            $usedKeys = [...$usedKeys, ...$matches[1]];
        }

        expect($usedKeys)->not->toBeEmpty();

        foreach (array_unique($usedKeys) as $key) {
            expect(Arr::has($documented, $key))->toBeTrue(
                "{$key} is used by a generated file but missing from the TODO"
            );
        }
    });

    it('never emits the removed Bearer-login contract', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        foreach ($this->writtenFiles as $relative) {
            expect(file_get_contents($this->root . '/' . $relative))
                ->not->toContain('Bearer"')
                ->not->toContain('BearerTokenResult')
                ->not->toContain('persistSession');
        }
    });

    it('spells the provenance marker so cspell can tokenize it', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('light-it')
            ->not->toContain('lightit');
    });

    it('tells the reader which i18n keys the generated schemas need', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('form.otp')
            ->toContain('form.recoveryCode');
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('lists the router and form libraries the screens import when the project lacks them', function (): void {
        $manifest = json_decode((string) file_get_contents($this->root . '/package.json'), true);
        unset(
            $manifest['dependencies']['@tanstack/react-router'],
            $manifest['dependencies']['react-hook-form'],
            $manifest['dependencies']['@hookform/resolvers'],
        );
        file_put_contents($this->root . '/package.json', json_encode($manifest));

        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root . '/AUTH-2FA-FRONTEND-TODO.md'))
            ->toContain('pnpm add @hookform/resolvers @tanstack/react-router react-hook-form')
            ->not->toContain('string-ts');
    });

    it('leaves a screen the project already has byte-identical and reports it as Skipped', function (): void {
        $existing = $this->root . '/src/routes/(public)/_guest/two-factor/page.tsx';
        mkdir(dirname($existing), 0755, true);
        file_put_contents($existing, "export const Route = {};\n");

        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

        $this->artisan('google2fa-frontend-fake')
            ->expectsOutputToContain('Skipped src/routes/(public)/_guest/two-factor/page.tsx')
            ->expectsOutputToContain('Created: src/routes/(public)/_guest/two-factor/setup/page.tsx')
            ->assertSuccessful();

        expect(file_get_contents($existing))->toBe("export const Route = {};\n");
    });

    it(
        'reports Skipped instead of Overwriting on a second run, and leaves every file byte-identical',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));
            $this->artisan('google2fa-frontend-fake')->assertSuccessful();

            $filesAfterFirstRun = [];
            foreach ($this->writtenFiles as $relative) {
                $filesAfterFirstRun[$relative] = file_get_contents($this->root . '/' . $relative);
            }

            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($this->root));

            $command = $this->artisan('google2fa-frontend-fake');

            foreach ($this->writtenFiles as $relative) {
                $command->expectsOutputToContain('Skipped ' . $relative);
            }

            $command->doesntExpectOutputToContain('Overwriting')->assertSuccessful();

            foreach ($filesAfterFirstRun as $relative => $contentsAfterFirstRun) {
                expect(file_get_contents($this->root . '/' . $relative))->toBe($contentsAfterFirstRun);
            }
        }
    );

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAFrontendCommand());

        $this->artisan('google2fa-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });

    it(
        'reports a clear error instead of warn-and-skip when --frontend-path points at a directory without React',
        function (): void {
            $invalidRoot = sys_get_temp_dir() . '/lightit-2fa-frontend-invalid-' . bin2hex(random_bytes(6));
            mkdir($invalidRoot, 0755, true);

            Artisan::registerCommand(new FakeGoogle2FAFrontendCommand($invalidRoot));

            $this->artisan('google2fa-frontend-fake')
                ->expectsOutputToContain('Invalid --frontend-path')
                ->assertSuccessful();

            File::deleteDirectory($invalidRoot);
        }
    );
});
