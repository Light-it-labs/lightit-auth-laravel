<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

/**
 * Owns files that more than one optional frontend feature depends on but that
 * no single feature's installer should generate on its own - generating it
 * from inside, say, Google2FAFrontendInstaller would leave a Passkeys-only or
 * Google-SSO-only install with an unresolvable import (see the frontend TODO
 * docs for the features that read from `@/services/auth/session`).
 *
 * Constructed with the full picture of what was selected (AuthSetupCommand
 * already has it before dispatching to any single feature's installer), so it
 * can pick the one composition every selected feature actually needs instead
 * of every feature trying to patch a file it does not fully own.
 */
final class SharedFrontendSeamInstaller implements AuthInstallerInterface
{
    private const SESSION_STUB = 'services/auth/session.ts.stub';

    private const SESSION_RELATIVE = 'src/services/auth/session.ts';

    private const SECURITY_PAGE_RELATIVE = 'src/routes/_private/security/page.tsx';

    private const SECURITY_PAGE_PASSKEYS_ONLY_STUB = 'routes/_private/security/page.passkeys-only.tsx.stub';

    private const SECURITY_PAGE_TWO_FACTOR_ONLY_STUB = 'routes/_private/security/page.two-factor-only.tsx.stub';

    private const SECURITY_PAGE_BOTH_STUB = 'routes/_private/security/page.both.tsx.stub';

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly FrontendProjectLocator $locator,
        private readonly string $laravelRoot,
        private readonly bool $needsSessionSeam,
        private readonly bool $needsPasskeysSecuritySection,
        private readonly bool $needsTwoFactorSecuritySection,
        private readonly ?string $frontendPath = null,
    ) {}

    public static function stubDirectory(): string
    {
        return __DIR__.'/../../Stubs/Frontend/Shared';
    }

    public function install(): void
    {
        if (! $this->needsSessionSeam && ! $this->needsSecurityPage()) {
            return;
        }

        $root = $this->locator->locate($this->laravelRoot, $this->frontendPath);

        if ($root === null) {
            $this->reportUnresolvedRoot();

            return;
        }

        $this->command->info("Frontend project resolved: {$root}");

        if ($this->needsSessionSeam) {
            $this->write($root, self::SESSION_STUB, self::SESSION_RELATIVE);
        }

        $securityPageStub = $this->securityPageStub();

        if ($securityPageStub !== null) {
            $this->write($root, $securityPageStub, self::SECURITY_PAGE_RELATIVE);
        }

        $this->command->info('Shared frontend seams generated.');
    }

    private function needsSecurityPage(): bool
    {
        return $this->needsPasskeysSecuritySection || $this->needsTwoFactorSecuritySection;
    }

    private function securityPageStub(): ?string
    {
        return match (true) {
            $this->needsPasskeysSecuritySection && $this->needsTwoFactorSecuritySection => self::SECURITY_PAGE_BOTH_STUB,
            $this->needsPasskeysSecuritySection => self::SECURITY_PAGE_PASSKEYS_ONLY_STUB,
            $this->needsTwoFactorSecuritySection => self::SECURITY_PAGE_TWO_FACTOR_ONLY_STUB,
            default => null,
        };
    }

    private function write(string $root, string $stub, string $relative): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        if (file_exists($destination)) {
            $this->command->warn("Overwriting: {$relative}");
        }

        $this->stubRenderer->renderTo(self::stubDirectory().'/'.$stub, $destination, FrontendStubTokens::defaults());

        $this->command->line("Created: {$relative}");
    }

    private function reportUnresolvedRoot(): void
    {
        if ($this->frontendPath !== null && $this->frontendPath !== '') {
            throw new RuntimeException(
                'Rejected --frontend-path: '.$this->locator->rejectionReason($this->frontendPath)
            );
        }

        $this->command->warn(
            'No React project found next to the application. Skipping the shared frontend seams. '
            .'Pass an explicit frontend path to generate them manually.'
        );
    }
}
