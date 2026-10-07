<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

use Illuminate\Console\Command;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

/**
 * What every frontend feature installer shares: resolving the React project, writing
 * stubs without overwriting, and reporting the dependencies the generated files need.
 */
final class FrontendStubWriter
{
    use LightitConsoleOutput;

    private const TWO_FACTOR_FRONTEND_FILES = [
        'src/stores/use-two-factor-challenge-store.ts',
        'src/services/auth/two-factor/types.ts',
        'src/routes/(public)/_guest/two-factor/page.tsx',
        'src/routes/(public)/_guest/two-factor/setup/page.tsx',
    ];

    public function __construct(
        protected Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
    ) {
        $this->initializeOutput($this->command);
    }

    public function locate(string $laravelRoot, string|null $frontendPath, string $featureLabel): string|null
    {
        $root = $this->locator->locate($laravelRoot, $frontendPath);

        if ($root !== null) {
            $this->command->info("Frontend project resolved: {$root}");

            return $root;
        }

        if ($frontendPath !== null && $frontendPath !== '') {
            $this->command->error(
                'Invalid --frontend-path: ' . $this->locator->rejectionReason($laravelRoot, $frontendPath)
            );

            return null;
        }

        $this->command->warn(
            "No React project found next to the application. Skipping the {$featureLabel} frontend step. "
            . 'Pass an explicit frontend path with --frontend-path=<path> to generate it manually.'
        );

        return null;
    }

    /**
     * @param array<string, string> $tokens
     */
    public function write(string $root, string $stubPath, string $relative, array $tokens): StubCopyOutcome
    {
        $destination = $this->locator->resolveDestination($root, $relative);
        $outcome = $this->stubRenderer->renderTo($stubPath, $destination, $tokens);

        match ($outcome) {
            StubCopyOutcome::Written => $this->command->line("Created: {$relative}"),
            StubCopyOutcome::Skipped => $this->printSkipped($relative),
        };

        return $outcome;
    }

    /**
     * @param list<string> $requiredDependencies
     *
     * @return list<string>
     */
    public function missingDependencies(string $root, array $requiredDependencies): array
    {
        $installed = $this->manifest->dependencies($root);

        return array_values(array_filter(
            $requiredDependencies,
            static fn (string $dependency): bool => ! \array_key_exists($dependency, $installed),
        ));
    }

    /**
     * @param list<string> $missingDependencies
     *
     * @return array<string, string>
     */
    public function tokens(string $root, array $missingDependencies): array
    {
        $dependencyReport = $missingDependencies === []
            ? FrontendStubTokens::defaults()['dependencyReport']
            : implode("\n", [
                'Missing dependencies. Run:',
                '',
                '```sh',
                $this->addCommand($root, $missingDependencies),
                '```',
            ]);

        return [
            ...FrontendStubTokens::defaults(),
            'packageManager' => $this->manifest->packageManager($root),
            'dependencyReport' => $dependencyReport,
        ];
    }

    /**
     * @param list<string> $missingDependencies
     */
    public function addCommand(string $root, array $missingDependencies): string
    {
        return $this->manifest->addCommand($root) . ' ' . implode(' ', $missingDependencies);
    }

    /**
     * Whether the 2FA challenge store, types and screens a sign-in hook needs to route a
     * challenge are already in the project.
     */
    public function hasTwoFactorFrontend(string $root): bool
    {
        foreach (self::TWO_FACTOR_FRONTEND_FILES as $relative) {
            if (! is_file($root . '/' . $relative)) {
                return false;
            }
        }

        return true;
    }
}
