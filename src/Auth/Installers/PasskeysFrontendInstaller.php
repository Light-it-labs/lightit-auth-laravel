<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubWriter;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;

final class PasskeysFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-PASSKEYS-FRONTEND-TODO.md';

    private const LOGIN_FORM_FILE = 'src/routes/(public)/_guest/login/-components/login-form.tsx';

    private const SIGN_IN_HOOK_STUB = 'routes/(public)/_guest/login/-hooks/use-sign-in-with-passkey';

    private const SIGN_IN_HOOK_FILE = 'src/routes/(public)/_guest/login/-hooks/use-sign-in-with-passkey.ts';

    private const TWO_FACTOR_FRONTEND_FILES = [
        'src/stores/use-two-factor-challenge-store.ts',
        'src/services/auth/two-factor/types.ts',
        'src/routes/(public)/_guest/two-factor/page.tsx',
        'src/routes/(public)/_guest/two-factor/setup/page.tsx',
    ];

    private const REQUIRED_DEPENDENCIES = [
        '@hookform/resolvers',
        '@simplewebauthn/browser',
        '@tanstack/react-query',
        '@tanstack/react-router',
        'axios',
        'date-fns',
        'react-hook-form',
        'sonner',
        'zod',
    ];

    private const FILES = [
        'services/auth/passkeys/types.ts.stub' => 'src/services/auth/passkeys/types.ts',
        'services/auth/passkeys/schemas.ts.stub' => 'src/services/auth/passkeys/schemas.ts',
        'services/auth/passkeys/api.ts.stub' => 'src/services/auth/passkeys/api.ts',
        'services/auth/passkeys/actions.ts.stub' => 'src/services/auth/passkeys/actions.ts',
        'routes/_private/account/passkeys/-hooks/use-passkey-errors.ts.stub' => 'src/routes/_private/account/passkeys/-hooks/use-passkey-errors.ts',
        'routes/_private/account/passkeys/-components/add-passkey-dialog.tsx.stub' => 'src/routes/_private/account/passkeys/-components/add-passkey-dialog.tsx',
        'routes/_private/account/passkeys/-components/rename-passkey-dialog.tsx.stub' => 'src/routes/_private/account/passkeys/-components/rename-passkey-dialog.tsx',
        'routes/_private/account/passkeys/-components/delete-passkey-dialog.tsx.stub' => 'src/routes/_private/account/passkeys/-components/delete-passkey-dialog.tsx',
        'routes/_private/account/passkeys/page.tsx.stub' => 'src/routes/_private/account/passkeys/page.tsx',
        'routes/(public)/_guest/login/-components/passkey-login-button.tsx.stub' => 'src/routes/(public)/_guest/login/-components/passkey-login-button.tsx',
    ];

    private readonly FrontendStubWriter $writer;

    public function __construct(
        protected Command $command,
        StubRenderer $stubRenderer,
        FrontendProjectLocator $locator,
        FrontendPackageManifest $manifest,
        private readonly string $laravelRoot,
        private readonly string|null $frontendPath = null,
    ) {
        $this->writer = new FrontendStubWriter($this->command, $stubRenderer, $locator, $manifest);
    }

    public static function stubDirectory(): string
    {
        return __DIR__ . '/../../Stubs/Frontend/Passkeys';
    }

    public function install(): void
    {
        $root = $this->writer->locate($this->laravelRoot, $this->frontendPath, 'passkeys');

        if ($root === null) {
            return;
        }

        $missing = $this->writer->missingDependencies($root, self::REQUIRED_DEPENDENCIES);
        $tokens = $this->writer->tokens($root, $missing);

        $signInHookStub = $this->hasTwoFactorFrontend($root)
            ? self::SIGN_IN_HOOK_STUB . '.two-factor.ts.stub'
            : self::SIGN_IN_HOOK_STUB . '.ts.stub';

        $files = [
            ...self::FILES,
            $signInHookStub => self::SIGN_IN_HOOK_FILE,
            self::TODO_FILE . '.stub' => self::TODO_FILE,
        ];

        foreach ($files as $stub => $relative) {
            $this->writer->write($root, self::stubDirectory() . '/' . $stub, $relative, $tokens);
        }

        $this->command->info('Frontend passkey services, account page and sign-in button generated.');

        if ($missing !== []) {
            $this->command->warn('Manual step: ' . $this->writer->addCommand($root, $missing));
        }

        $this->printLoginFormManualStep();

        $this->command->warn(
            'Manual step: add the passkeys i18n block listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.'
        );
    }

    private function hasTwoFactorFrontend(string $root): bool
    {
        foreach (self::TWO_FACTOR_FRONTEND_FILES as $relative) {
            if (! is_file($root . '/' . $relative)) {
                return false;
            }
        }

        return true;
    }

    private function printLoginFormManualStep(): void
    {
        $this->command->warn('Manual step: add the passkey sign-in button to the login form.');
        $this->command->line('In ' . self::LOGIN_FORM_FILE . ':');
        $this->command->line(
            '  1. Add, as the last import:   import { PasskeyLoginButton } from "./passkey-login-button";'
        );
        $this->command->line(
            '  2. Add, on the line after the submit button\'s closing </Button> (the one showing'
            . ' {t("login.login")}):   <PasskeyLoginButton />'
        );
    }
}
