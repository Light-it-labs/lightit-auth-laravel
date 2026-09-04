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

final class PasskeysFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'AUTH-PASSKEYS-FRONTEND-TODO.md';

    private const REQUIRED_DEPENDENCIES = [
        '@tanstack/react-query',
        'axios',
        'string-ts',
        'zod',
    ];

    private const FILES = [
        'services/auth/passkeys/types.ts.stub' => 'src/services/auth/passkeys/types.ts',
        'services/auth/passkeys/schemas.ts.stub' => 'src/services/auth/passkeys/schemas.ts',
        'services/auth/passkeys/api.ts.stub' => 'src/services/auth/passkeys/api.ts',
        'services/auth/passkeys/actions.ts.stub' => 'src/services/auth/passkeys/actions.ts',
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
        return __DIR__.'/../../Stubs/Frontend/Passkeys';
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
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->write($root, self::TODO_FILE.'.stub', self::TODO_FILE, $tokens);

        $this->command->info(
            'Frontend passkeys layer generated. Read '.self::TODO_FILE.' before building the screens.'
        );
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        if (file_exists($destination)) {
            $this->command->warn("Overwriting: {$relative}");
        }

        $this->stubRenderer->renderTo(self::stubDirectory().'/'.$stub, $destination, $tokens, $this->originMarker);

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
            'No React project found next to the application. Skipping the passkeys frontend step. '
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
