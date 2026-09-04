<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendStubTokens;
use Lightitlabs\Auth\Frontend\FrontendUsageScanner;
use Lightitlabs\Auth\Permissions\PermissionCatalog;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubRenderer;
use RuntimeException;

final class LaravelPermissionFrontendInstaller implements AuthInstallerInterface
{
    private const TODO_FILE = 'ROLES-PERMISSIONS-FRONTEND-TODO.md';

    private const REQUIRED_DEPENDENCIES = [
        '@tanstack/react-query',
        '@tanstack/react-router',
    ];

    /**
     * Any call site already invoking a same-named check by hand, before this
     * layer exists to consolidate it into one guard.
     */
    private const INLINE_PERMISSION_CHECK_PATTERN = '/\bhasPermission\(/';

    private const CURRENT_USER_QUERY_PROBE_PATH = 'src/services/auth/factories.ts';

    private const FILES = [
        'services/permissions/constants.ts.stub' => 'src/services/permissions/constants.ts',
        'services/permissions/use-permissions.ts.stub' => 'src/services/permissions/use-permissions.ts',
        'services/permissions/ensure-permission.ts.stub' => 'src/services/permissions/ensure-permission.ts',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly StubRenderer $stubRenderer,
        private readonly OriginMarker $originMarker,
        private readonly FrontendProjectLocator $locator,
        private readonly FrontendPackageManifest $manifest,
        private readonly string $laravelRoot,
        private readonly ?string $frontendPath = null,
        private readonly PermissionCatalog $permissionCatalog = new PermissionCatalog,
        private readonly FrontendUsageScanner $usageScanner = new FrontendUsageScanner,
    ) {}

    public static function stubDirectory(): string
    {
        return __DIR__.'/../../Stubs/Frontend/LaravelPermissions';
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
            'Frontend roles and permissions layer generated. Read '.self::TODO_FILE.' before wiring route guards.'
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
            'No React project found next to the application. Skipping the roles and permissions frontend step. '
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
            'permissionConstants' => $this->permissionCatalog->toTypeScriptConstants(),
            'permissionCallSites' => $this->usageScanner->toMarkdownList(
                $this->usageScanner->grep($root, self::INLINE_PERMISSION_CHECK_PATTERN)
            ),
            'currentUserQueryProbeNote' => $this->currentUserQueryProbeNote($root),
        ];
    }

    private function currentUserQueryProbeNote(string $root): string
    {
        if (is_file($root.'/'.self::CURRENT_USER_QUERY_PROBE_PATH)) {
            return '';
        }

        return 'No `'.self::CURRENT_USER_QUERY_PROBE_PATH.'` was found at the resolved frontend root - '
            .'the import above is a best guess. Repoint `currentUserQueryImportPath` in both files above '
            .'to wherever your current-user query factory actually lives.';
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
