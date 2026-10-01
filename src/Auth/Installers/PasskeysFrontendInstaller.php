<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

final class PasskeysFrontendInstaller implements AuthInstallerInterface
{
    use LightitConsoleOutput;

    private const TODO_FILE = 'AUTH-PASSKEYS-FRONTEND-TODO.md';

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
    ];

    public function __construct(
        protected Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
        private readonly string $laravelRoot,
        private readonly string|null $frontendPath = null,
    ) {
        $this->initializeOutput($this->command);
    }

    public static function stubDirectory(): string
    {
        return __DIR__ . '/../../Stubs/Frontend/Passkeys';
    }

    public function install(): void
    {
        $root = $this->locator->locate($this->laravelRoot, $this->frontendPath);

        if ($root === null) {
            $this->reportUnresolvedRoot();

            return;
        }

        $this->command->info("Frontend project resolved: {$root}");

        $missing = $this->missingDependencies($root);
        $tokens = [
            ...FrontendStubTokens::defaults(),
            'packageManager' => $this->manifest->packageManager($root),
            'dependencyReport' => $this->dependencyReport($root, $missing),
        ];

        foreach ([...self::FILES, self::TODO_FILE . '.stub' => self::TODO_FILE] as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->command->info('Frontend passkey services and account page generated.');

        if ($missing !== []) {
            $this->command->warn('Manual step: ' . $this->addCommand($root, $missing));
        }

        $this->command->warn(
            'Manual step: add the passkeys i18n block listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.'
        );
    }

    /**
     * @param array<string, string> $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        match ($this->stubRenderer->renderTo(self::stubDirectory() . '/' . $stub, $destination, $tokens)) {
            StubCopyOutcome::Written => $this->command->line("Created: {$relative}"),
            StubCopyOutcome::Skipped => $this->printSkipped($relative),
        };
    }

    private function reportUnresolvedRoot(): void
    {
        if ($this->frontendPath !== null && $this->frontendPath !== '') {
            $this->command->error(
                'Invalid --frontend-path: ' . $this->locator->rejectionReason($this->laravelRoot, $this->frontendPath)
            );

            return;
        }

        $this->command->warn(
            'No React project found next to the application. Skipping the passkeys frontend step. '
            . 'Pass an explicit frontend path with --frontend-path=<path> to generate it.'
        );
    }

    /**
     * @return list<string>
     */
    private function missingDependencies(string $root): array
    {
        $installed = $this->manifest->dependencies($root);

        return array_values(array_filter(
            self::REQUIRED_DEPENDENCIES,
            static fn (string $dependency): bool => ! \array_key_exists($dependency, $installed),
        ));
    }

    /**
     * @param list<string> $missing
     */
    private function dependencyReport(string $root, array $missing): string
    {
        if ($missing === []) {
            return FrontendStubTokens::defaults()['dependencyReport'];
        }

        return implode("\n", ['Missing dependencies. Run:', '', '```sh', $this->addCommand($root, $missing), '```']);
    }

    /**
     * @param list<string> $missing
     */
    private function addCommand(string $root, array $missing): string
    {
        return $this->manifest->addCommand($root) . ' ' . implode(' ', $missing);
    }
}
