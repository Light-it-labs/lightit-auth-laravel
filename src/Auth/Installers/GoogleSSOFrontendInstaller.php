<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Auth\Frontend\TypeScriptPatcher;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

final class GoogleSSOFrontendInstaller implements AuthInstallerInterface
{
    private const ENV_FILE = 'src/config/env.ts';

    private const FILES = [
        'services/auth/sso/google/types.ts.stub' => 'src/services/auth/sso/google/types.ts',
        'services/auth/sso/google/schemas.ts.stub' => 'src/services/auth/sso/google/schemas.ts',
        'services/auth/sso/google/api.ts.stub' => 'src/services/auth/sso/google/api.ts',
        'services/auth/sso/google/actions.ts.stub' => 'src/services/auth/sso/google/actions.ts',
        'hooks/use-google-identity-services.ts.stub' => 'src/hooks/use-google-identity-services.ts',
        'routes/(public)/_guest/login/-components/google-login-button.tsx.stub' => 'src/routes/(public)/_guest/login/-components/google-login-button.tsx',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly TypeScriptPatcher $typeScriptPatcher,
        private readonly FrontendProjectLocator $locator,
        private readonly string $laravelRoot,
        private readonly ?string $frontendPath = null,
    ) {}

    public static function stubDirectory(): string
    {
        return __DIR__.'/../../Stubs/Frontend/GoogleSSO';
    }

    public function install(): void
    {
        $root = $this->locator->locate($this->laravelRoot, $this->frontendPath);

        if ($root === null) {
            $this->reportUnresolvedRoot();

            return;
        }

        $this->command->info("Frontend project resolved: {$root}");

        foreach (self::FILES as $stub => $relative) {
            $this->write($root, $stub, $relative);
        }

        $this->patchEnv($root);

        $this->command->info(
            'Frontend Google sign-in button generated. Set VITE_GOOGLE_CLIENT_ID in your .env before using it.'
        );
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

    private function patchEnv(string $root): void
    {
        $envPath = $this->locator->resolveDestination($root, self::ENV_FILE);
        $outcome = $this->typeScriptPatcher->addGoogleClientIdToEnv($envPath);

        if ($outcome->needsManualStep()) {
            $this->command->warn(
                'Could not add VITE_GOOGLE_CLIENT_ID to '.self::ENV_FILE.' automatically ('.$outcome->name.'). '
                .'Add it to the client schema by hand.'
            );

            return;
        }

        $this->command->line('Patched: '.self::ENV_FILE);
    }

    private function reportUnresolvedRoot(): void
    {
        if ($this->frontendPath !== null && $this->frontendPath !== '') {
            throw new RuntimeException(
                'Rejected --frontend-path: '.$this->locator->rejectionReason($this->frontendPath)
            );
        }

        $this->command->warn(
            'No React project found next to the application. Skipping the Google sign-in button step. '
            .'Pass an explicit frontend path to generate it manually.'
        );
    }
}
