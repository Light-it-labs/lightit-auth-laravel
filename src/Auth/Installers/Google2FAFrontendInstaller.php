<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Contracts\SetupReporter;
use Lightitlabs\Tools\StubCopyOutcome;
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

    public function __construct(
        private readonly SetupReporter $reporter,
        private readonly StubRenderer $stubRenderer,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
        private readonly string $laravelRoot,
        private readonly string|null $frontendPath = null,
    ) {
    }

    public static function stubDirectory(): string
    {
        return __DIR__ . '/../../Stubs/Frontend/Google2FA';
    }

    public function install(): void
    {
        $root = $this->locator->locate($this->laravelRoot, $this->frontendPath);

        if ($root === null) {
            $this->reportUnresolvedRoot();

            return;
        }

        $tokens = $this->tokens($root);

        foreach (self::FILES as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->write($root, self::TODO_FILE . '.stub', self::TODO_FILE, $tokens);

        $this->reportLoginFormManualStep();
        $this->reportSidebarManualStep();
    }

    private function reportSidebarManualStep(): void
    {
        $this->reporter->manualStep('Manual step: link the account page from the sidebar.', [
            'In ' . self::SIDEBAR_FILE . ', add at the end of the links array:',
            '  ' . self::SIDEBAR_LINK,
        ]);
    }

    private function reportLoginFormManualStep(): void
    {
        $this->reporter->manualStep('Manual step: route the login form through the 2FA-aware login hook.', [
            'In ' . self::LOGIN_FORM_FILE . ':',
            '  1. Remove:   import { useLogin } from "@/services/auth/actions";',
            '  2. Add, after the "@/utils" import:   import { useTwoFactorLogin } from "../-hooks/use-two-factor-login";',
            '  3. Replace:  const loginMutation = useLogin();',
            '     with:     const loginMutation = useTwoFactorLogin();',
            'Then add the i18n keys listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.',
        ]);
    }

    /**
     * @param array<string, string> $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        $outcome = $this->stubRenderer->renderTo(self::stubDirectory() . '/' . $stub, $destination, $tokens);

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($relative),
            StubCopyOutcome::Skipped => $this->reporter->skipped($relative),
        };
    }

    private function reportUnresolvedRoot(): void
    {
        if ($this->frontendPath !== null && $this->frontendPath !== '') {
            $this->reporter->warning(
                'Invalid --frontend-path: ' . $this->locator->rejectionReason($this->laravelRoot, $this->frontendPath)
            );

            return;
        }

        $this->reporter->warning(
            'No React project found next to the application. Skipping the 2FA frontend step. '
            . 'Pass an explicit frontend path with --frontend-path=<path> to generate it manually.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function tokens(string $root): array
    {
        return [
            ...FrontendStubTokens::defaults(),
            'packageManager' => $this->manifest->packageManager($root),
            'dependencyReport' => $this->dependencyReport($root),
        ];
    }

    private function dependencyReport(string $root): string
    {
        $installed = $this->manifest->dependencies($root);

        $missing = array_values(array_filter(
            self::REQUIRED_DEPENDENCIES,
            static function (string $dependency) use ($installed): bool {
                return ! \array_key_exists($dependency, $installed);
            }
        ));

        if ($missing === []) {
            return FrontendStubTokens::defaults()['dependencyReport'];
        }

        return trim(implode("\n", [
            'Missing dependencies. Run:',
            '',
            '```sh',
            $this->manifest->addCommand($root) . ' ' . implode(' ', $missing),
            '```',
        ]));
    }
}
