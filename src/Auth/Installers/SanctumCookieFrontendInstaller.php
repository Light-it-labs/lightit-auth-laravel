<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Auth\Frontend\FrontendUsageScanner;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

final class SanctumCookieFrontendInstaller implements AuthInstallerInterface
{
    public const AUTH_STORE_PATTERN = '/use-auth-store/';

    public const CLIENT_PATTERN = '/\b(?:publicApi|privateApi)\b/';

    private const AXIOS_VERSION_FLOOR = '1.6.2';

    private const REQUIRED_DEPENDENCIES = [
        '@lukemorales/query-key-factory',
        '@tanstack/react-query',
        'axios',
        'string-ts',
        'zod',
    ];

    private const TODO_FILE = 'AUTH-FRONTEND-TODO.md';

    private const UNTOKENISED_FILES = [
        'services/auth/actions.ts.stub' => 'src/services/auth/actions.ts',
        'services/auth/factories.ts.stub' => 'src/services/auth/factories.ts',
        'services/auth/types.ts.stub' => 'src/services/auth/types.ts',
    ];

    private const TOKENISED_FILES = [
        'config/api.ts.stub' => 'src/config/api.ts',
        'services/auth/api.ts.stub' => 'src/services/auth/api.ts',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
        private readonly FrontendUsageScanner $scanner,
        private readonly string $laravelRoot,
        private readonly string|null $frontendPath = null,
    ) {
    }

    public static function stubDirectory(): string
    {
        return __DIR__ . '/../../Stubs/Frontend/SanctumCookie';
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

        foreach ([...self::TOKENISED_FILES, ...self::UNTOKENISED_FILES] as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->write($root, $this->schemaStub($root), 'src/services/auth/schemas.ts', $tokens);

        // Scanned only once the generated files are in place, so the lists carry
        // the work the consumer still owes and not the files just rewritten.
        $this->write($root, self::TODO_FILE . '.stub', self::TODO_FILE, [
            ...$tokens,
            ...$this->scanTokens($root),
        ]);

        $this->command->info(
            'Frontend cookie auth layer generated. Read ' . self::TODO_FILE . ' before running the app.'
        );
    }

    /**
     * @param array<string, string> $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        if (file_exists($destination)) {
            $this->command->warn("Overwriting: {$relative}");
        }

        $this->stubRenderer->renderTo(self::stubDirectory() . '/' . $stub, $destination, $tokens);

        $this->command->line("Created: {$relative}");
    }

    private function reportUnresolvedRoot(): void
    {
        if ($this->frontendPath !== null && $this->frontendPath !== '') {
            throw new RuntimeException(
                'Rejected --frontend-path: ' . $this->locator->rejectionReason($this->frontendPath)
            );
        }

        $this->command->warn(
            'No React project found next to the application. Skipping the frontend step. '
            . 'Pass --frontend-path to point at it explicitly.'
        );
    }

    private function schemaStub(string $root): string
    {
        return 'services/auth/schemas.zod' . $this->zodMajor($root) . '.ts.stub';
    }

    private function zodMajor(string $root): int
    {
        $constraint = $this->manifest->dependencies($root)['zod'] ?? null;
        $major = $constraint === null ? null : $this->manifest->majorVersion($constraint);

        return $major !== null && $major <= 3 ? 3 : 4;
    }

    /**
     * @return array<string, string>
     */
    private function tokens(string $root): array
    {
        return [
            ...FrontendStubTokens::defaults(),
            'dependencyReport' => $this->dependencyReport($root),
            'packageManager' => $this->manifest->packageManager($root),
            'zodMajor' => (string) $this->zodMajor($root),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function scanTokens(string $root): array
    {
        return [
            'authStoreImporters' => $this->scanner->toMarkdownList(
                $this->scanner->grep($root, self::AUTH_STORE_PATTERN)
            ),
            'apiCallSites' => $this->scanner->toMarkdownList(
                $this->scanner->grep($root, self::CLIENT_PATTERN)
            ),
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

        $lines = [];

        if ($missing !== []) {
            $lines[] = 'Missing dependencies. Run:';
            $lines[] = '';
            $lines[] = '```sh';
            $lines[] = $this->manifest->addCommand($root) . ' ' . implode(' ', $missing);
            $lines[] = '```';
        }

        $axios = $installed['axios'] ?? null;

        if ($axios !== null && ! $this->manifest->satisfiesFloor($axios, self::AXIOS_VERSION_FLOOR)) {
            $lines[] = '';
            $lines[] = sprintf(
                'axios is pinned at `%s`. `withXSRFToken` needs `%s` or newer, so the header is never sent as things stand. Run:',
                $axios,
                self::AXIOS_VERSION_FLOOR
            );
            $lines[] = '';
            $lines[] = '```sh';
            $lines[] = $this->manifest->addCommand($root) . ' axios@^' . self::AXIOS_VERSION_FLOOR;
            $lines[] = '```';
        }

        if ($lines === []) {
            return FrontendStubTokens::defaults()['dependencyReport'];
        }

        return trim(implode("\n", $lines));
    }
}
