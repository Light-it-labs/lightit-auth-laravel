<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubWriter;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;

final class Google2FAFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-2FA-FRONTEND-TODO.md';

    private const REQUIRED_DEPENDENCIES = [
        '@hookform/resolvers',
        '@tanstack/react-query',
        '@tanstack/react-router',
        'axios',
        'react-hook-form',
        'sonner',
        'string-ts',
        'zod',
        'zustand',
    ];

    private const LOGIN_FORM_FILE = 'src/routes/(public)/_guest/login/-components/login-form.tsx';

    private const SIDEBAR_FILE = 'src/routes/_private/-components/sidebar/sidebar.tsx';

    private const SIDEBAR_LINK = '{ path: "/account/two-factor", label: t("navigation.links.twoFactor"), icon: <Icons.Lock /> },';

    private const FILES = [
        'services/auth/two-factor/types.ts.stub' => 'src/services/auth/two-factor/types.ts',
        'services/auth/two-factor/schemas.ts.stub' => 'src/services/auth/two-factor/schemas.ts',
        'services/auth/two-factor/api.ts.stub' => 'src/services/auth/two-factor/api.ts',
        'services/auth/two-factor/actions.ts.stub' => 'src/services/auth/two-factor/actions.ts',
        'stores/use-two-factor-challenge-store.ts.stub' => 'src/stores/use-two-factor-challenge-store.ts',
        'components/two-factor/authenticator-secret.tsx.stub' => 'src/components/two-factor/authenticator-secret.tsx',
        'components/two-factor/recovery-codes.tsx.stub' => 'src/components/two-factor/recovery-codes.tsx',
        'routes/(public)/_guest/login/-hooks/use-two-factor-login.ts.stub' => 'src/routes/(public)/_guest/login/-hooks/use-two-factor-login.ts',
        'routes/(public)/_guest/two-factor/-hooks/use-two-factor-completion.ts.stub' => 'src/routes/(public)/_guest/two-factor/-hooks/use-two-factor-completion.ts',
        'routes/(public)/_guest/two-factor/-components/one-time-password-form.tsx.stub' => 'src/routes/(public)/_guest/two-factor/-components/one-time-password-form.tsx',
        'routes/(public)/_guest/two-factor/-components/recovery-code-form.tsx.stub' => 'src/routes/(public)/_guest/two-factor/-components/recovery-code-form.tsx',
        'routes/(public)/_guest/two-factor/page.tsx.stub' => 'src/routes/(public)/_guest/two-factor/page.tsx',
        'routes/(public)/_guest/two-factor/setup/page.tsx.stub' => 'src/routes/(public)/_guest/two-factor/setup/page.tsx',
        'routes/_private/account/two-factor/-hooks/use-two-factor-account-errors.ts.stub' => 'src/routes/_private/account/two-factor/-hooks/use-two-factor-account-errors.ts',
        'routes/_private/account/two-factor/-components/password-confirmation-form.tsx.stub' => 'src/routes/_private/account/two-factor/-components/password-confirmation-form.tsx',
        'routes/_private/account/two-factor/-components/second-factor-confirmation-form.tsx.stub' => 'src/routes/_private/account/two-factor/-components/second-factor-confirmation-form.tsx',
        'routes/_private/account/two-factor/-components/confirm-two-factor-form.tsx.stub' => 'src/routes/_private/account/two-factor/-components/confirm-two-factor-form.tsx',
        'routes/_private/account/two-factor/-components/enable-two-factor-dialog.tsx.stub' => 'src/routes/_private/account/two-factor/-components/enable-two-factor-dialog.tsx',
        'routes/_private/account/two-factor/-components/regenerate-recovery-codes-dialog.tsx.stub' => 'src/routes/_private/account/two-factor/-components/regenerate-recovery-codes-dialog.tsx',
        'routes/_private/account/two-factor/-components/disable-two-factor-dialog.tsx.stub' => 'src/routes/_private/account/two-factor/-components/disable-two-factor-dialog.tsx',
        'routes/_private/account/two-factor/page.tsx.stub' => 'src/routes/_private/account/two-factor/page.tsx',
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
        return __DIR__ . '/../../Stubs/Frontend/Google2FA';
    }

    public function install(): void
    {
        $root = $this->writer->locate($this->laravelRoot, $this->frontendPath, '2FA');

        if ($root === null) {
            return;
        }

        $tokens = $this->writer->tokens($root, $this->writer->missingDependencies($root, self::REQUIRED_DEPENDENCIES));

        $this->writer->writeTwoFactorChallengeRouting($root, $tokens);

        foreach ([...self::FILES, self::TODO_FILE . '.stub' => self::TODO_FILE] as $stub => $relative) {
            $this->writer->write($root, self::stubDirectory() . '/' . $stub, $relative, $tokens);
        }

        $this->command->info('Frontend two-factor authentication services, login screens and account page generated.');

        $this->printLoginFormManualStep();
        $this->printSidebarManualStep();
    }

    private function printSidebarManualStep(): void
    {
        $this->command->warn('Manual step: link the account page from the sidebar.');
        $this->command->line('In ' . self::SIDEBAR_FILE . ', add at the end of the links array:');
        $this->command->line('  ' . self::SIDEBAR_LINK);
    }

    private function printLoginFormManualStep(): void
    {
        $this->command->warn('Manual step: route the login form through the 2FA-aware login hook.');
        $this->command->line('In ' . self::LOGIN_FORM_FILE . ':');
        $this->command->line('  1. Remove:   import { useLogin } from "@/services/auth/actions";');
        $this->command->line(
            '  2. Add, after the "@/utils" import:   import { useTwoFactorLogin } from "../-hooks/use-two-factor-login";'
        );
        $this->command->line('  3. Replace:  const loginMutation = useLogin();');
        $this->command->line('     with:     const loginMutation = useTwoFactorLogin();');
        $this->command->line('Then add the i18n keys listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.');
    }
}
