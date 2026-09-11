<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

final class Google2FAFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-2FA-FRONTEND-TODO.md';

    private const REQUIRED_DEPENDENCIES = [
        '@tanstack/react-query',
        '@tanstack/react-router',
        'axios',
        'string-ts',
        'zod',
    ];

    private const FILES = [
        'services/auth/two-factor/types.ts.stub' => 'src/services/auth/two-factor/types.ts',
        'services/auth/two-factor/schemas.ts.stub' => 'src/services/auth/two-factor/schemas.ts',
        'services/auth/two-factor/api.ts.stub' => 'src/services/auth/two-factor/api.ts',
        'services/auth/two-factor/actions.ts.stub' => 'src/services/auth/two-factor/actions.ts',
    ];

    private const SESSION_STUB = __DIR__.'/../../Stubs/Frontend/Shared/services/auth/session.ts.stub';

    private const SESSION_RELATIVE = 'src/services/auth/session.ts';

    /**
     * Unlike the service layer and the TODO doc above, screens are not stamped with
     * the provenance marker - they are meant to be edited freely as soon as they
     * land, not recognised later as this package's own output.
     */
    private const SCREEN_FILES = [
        'routes/(public)/_guest/two-factor/setup/page.tsx.stub' => 'src/routes/(public)/_guest/two-factor/setup/page.tsx',
        'routes/(public)/_guest/two-factor/-components/recovery-codes.tsx.stub' => 'src/routes/(public)/_guest/two-factor/-components/recovery-codes.tsx',
        'routes/(public)/_guest/two-factor/page.tsx.stub' => 'src/routes/(public)/_guest/two-factor/page.tsx',
        'routes/(public)/_guest/two-factor/recovery-code/page.tsx.stub' => 'src/routes/(public)/_guest/two-factor/recovery-code/page.tsx',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly OriginMarker $originMarker,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
        private readonly string $laravelRoot,
        private readonly ?string $frontendPath = null,
    ) {}

    public static function stubDirectory(): string
    {
        return __DIR__.'/../../Stubs/Frontend/Google2FA';
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
            $this->write($root, self::stubDirectory().'/'.$stub, $relative, $tokens, $this->originMarker);
        }

        foreach (self::SCREEN_FILES as $stub => $relative) {
            $this->write($root, self::stubDirectory().'/'.$stub, $relative, $tokens);
        }

        $this->write($root, self::SESSION_STUB, self::SESSION_RELATIVE, $tokens);

        $this->write($root, self::stubDirectory().'/'.self::TODO_FILE.'.stub', self::TODO_FILE, $tokens, $this->originMarker);

        $this->command->info(
            'Frontend two-factor authentication layer and login screens generated. Read '
            .self::TODO_FILE.' before building the remaining account-management screens.'
        );
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function write(string $root, string $stubPath, string $relative, array $tokens, ?OriginMarker $marker = null): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        if (file_exists($destination)) {
            $this->command->warn("Overwriting: {$relative}");
        }

        $this->stubRenderer->renderTo($stubPath, $destination, $tokens, $marker);

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
            'No React project found next to the application. Skipping the 2FA frontend step. '
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
