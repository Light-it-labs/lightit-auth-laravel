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

        foreach ([...self::FILES, self::TODO_FILE . '.stub' => self::TODO_FILE] as $stub => $relative) {
            $this->writer->write($root, self::stubDirectory() . '/' . $stub, $relative, $tokens);
        }

        $this->command->info('Frontend passkey services and account page generated.');

        if ($missing !== []) {
            $this->command->warn('Manual step: ' . $this->writer->addCommand($root, $missing));
        }

        $this->command->warn(
            'Manual step: add the passkeys i18n block listed in ' . self::TODO_FILE . ' to src/i18n/locales/en.json.'
        );
    }
}
