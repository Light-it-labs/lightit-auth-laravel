<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Auth\Frontend\TypeScriptPatcher;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

final class GoogleSSOFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-GOOGLE-SSO-FRONTEND-TODO.md';

    private const ENV_FILE = 'src/config/env.ts';

    private const REQUIRED_DEPENDENCIES = [
        '@tanstack/react-query',
        'axios',
        'string-ts',
        'zod',
    ];

    private const FILES = [
        'services/auth/sso/google/types.ts.stub' => 'src/services/auth/sso/google/types.ts',
        'services/auth/sso/google/schemas.ts.stub' => 'src/services/auth/sso/google/schemas.ts',
        'services/auth/sso/google/api.ts.stub' => 'src/services/auth/sso/google/api.ts',
        'services/auth/sso/google/actions.ts.stub' => 'src/services/auth/sso/google/actions.ts',
    ];

    /**
     * Screens and hooks, unlike the service layer and the TODO doc above, are not
     * stamped with the provenance marker - they are meant to be edited freely as
     * soon as they land, not recognised later as this package's own output.
     */
    private const SCREEN_FILES = [
        'hooks/use-google-identity-services.ts.stub' => 'src/hooks/use-google-identity-services.ts',
        'routes/(public)/_guest/login/-components/google-login-button.tsx.stub' => 'src/routes/(public)/_guest/login/-components/google-login-button.tsx',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly OriginMarker $originMarker,
        private readonly TypeScriptPatcher $typeScriptPatcher,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
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

        $tokens = $this->tokens($root);

        foreach (self::FILES as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens, $this->originMarker);
        }

        $this->write($root, self::TODO_FILE.'.stub', self::TODO_FILE, $tokens, $this->originMarker);

        foreach (self::SCREEN_FILES as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->patchEnv($root);

        $this->command->info(
            'Frontend Google SSO layer generated. Read '.self::TODO_FILE.' before building the screens.'
        );
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens, ?OriginMarker $marker = null): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        if (file_exists($destination)) {
            $this->command->warn("Overwriting: {$relative}");
        }

        $this->stubRenderer->renderTo(self::stubDirectory().'/'.$stub, $destination, $tokens, $marker);

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
            'No React project found next to the application. Skipping the Google SSO frontend step. '
            .'Pass an explicit frontend path to generate it manually.'
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
            $this->manifest->addCommand($root).' '.implode(' ', $missing),
            '```',
        ]));
    }
}
