<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubWriter;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;

final class SocialLoginFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-SOCIAL-FRONTEND-TODO.md';

    private const LOGIN_FORM_FILE = 'src/routes/(public)/_guest/login/-components/login-form.tsx';

    private const SIGN_IN_HOOK_STUB = 'routes/(public)/_guest/login/-hooks/use-sign-in-with-google';

    private const SIGN_IN_HOOK_FILE = 'src/routes/(public)/_guest/login/-hooks/use-sign-in-with-google.ts';

    private const REQUIRED_DEPENDENCIES = [
        '@tanstack/react-query',
        '@tanstack/react-router',
        'axios',
        'sonner',
    ];

    private const FILES = [
        'services/auth/social/types.ts.stub' => 'src/services/auth/social/types.ts',
        'services/auth/social/api.ts.stub' => 'src/services/auth/social/api.ts',
        'services/auth/social/google-identity.ts.stub' => 'src/services/auth/social/google-identity.ts',
        'routes/(public)/_guest/login/-components/google-login-button.tsx.stub' => 'src/routes/(public)/_guest/login/-components/google-login-button.tsx',
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
        return __DIR__ . '/../../Stubs/Frontend/SocialLogin';
    }

    public function install(): void
    {
        $root = $this->writer->locate($this->laravelRoot, $this->frontendPath, 'social login');

        if ($root === null) {
            return;
        }

        $missing = $this->writer->missingDependencies($root, self::REQUIRED_DEPENDENCIES);
        $tokens = $this->writer->tokens($root, $missing);

        $hasTwoFactorFrontend = $this->writer->hasTwoFactorFrontend($root);
        $signInHookStub = $hasTwoFactorFrontend
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

        if ($hasTwoFactorFrontend) {
            $this->writer->writeTwoFactorChallengeRouting($root, $tokens);
        }

        $this->command->info('Frontend social login service and "Sign in with Google" button generated.');

        if ($missing !== []) {
            $this->command->warn('Manual step: ' . $this->writer->addCommand($root, $missing));
        }

        $this->printClientIdManualStep();
        $this->printLoginFormManualStep();

        $this->command->warn(
            'Manual step: add the socialLogin i18n block listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.'
        );
    }

    private function printClientIdManualStep(): void
    {
        $this->command->warn('Manual step: set the Google client id (the backend\'s GOOGLE_CLIENT_ID).');
        $this->command->line('  1. In .env:   VITE_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com');
        $this->command->line('  2. In src/config/env.ts, inside client:   VITE_GOOGLE_CLIENT_ID: z.string().min(1),');
        $this->command->line('  3. In every deploy workflow that builds the app, next to the other VITE_* variables.');
    }

    private function printLoginFormManualStep(): void
    {
        $this->command->warn('Manual step: add the "Sign in with Google" button to the login form.');
        $this->command->line('In ' . self::LOGIN_FORM_FILE . ':');
        $this->command->line(
            '  1. Add, as the last import (right before the PasskeyLoginButton import if you have it):   '
            . 'import { GoogleLoginButton } from "./google-login-button";'
        );
        $this->command->line(
            '  2. Add, on the line after the submit button\'s closing </Button> (the one showing'
            . ' {t("login.login")}), below <PasskeyLoginButton /> if you have it:   <GoogleLoginButton />'
        );
    }
}
