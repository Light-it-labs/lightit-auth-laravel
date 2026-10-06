<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

final class LaravelPermissionFrontendInstaller implements AuthInstallerInterface
{
    use LightitConsoleOutput;

    private const TODO_FILE = 'AUTH-ROLES-FRONTEND-TODO.md';

    private const REQUIRED_DEPENDENCIES = [
        '@lukemorales/query-key-factory',
        '@tanstack/react-query',
        '@tanstack/react-router',
        '@tanstack/react-table',
        'axios',
        'sonner',
        'zod',
    ];

    private const SIDEBAR_FILE = 'src/routes/_private/-components/sidebar/sidebar.tsx';

    private const FILES = [
        'services/permissions/constants.ts.stub' => 'src/services/permissions/constants.ts',
        'services/permissions/schemas.ts.stub' => 'src/services/permissions/schemas.ts',
        'services/permissions/types.ts.stub' => 'src/services/permissions/types.ts',
        'services/permissions/api.ts.stub' => 'src/services/permissions/api.ts',
        'services/permissions/factories.ts.stub' => 'src/services/permissions/factories.ts',
        'services/permissions/actions.ts.stub' => 'src/services/permissions/actions.ts',
        'services/permissions/ensure-permission.ts.stub' => 'src/services/permissions/ensure-permission.ts',
        'services/roles/schemas.ts.stub' => 'src/services/roles/schemas.ts',
        'services/roles/types.ts.stub' => 'src/services/roles/types.ts',
        'services/roles/api.ts.stub' => 'src/services/roles/api.ts',
        'services/roles/factories.ts.stub' => 'src/services/roles/factories.ts',
        'services/roles/actions.ts.stub' => 'src/services/roles/actions.ts',
        'components/permissions/can.tsx.stub' => 'src/components/permissions/can.tsx',
        'routes/_private/roles/-hooks/use-user-roles-table.tsx.stub' => 'src/routes/_private/roles/-hooks/use-user-roles-table.tsx',
        'routes/_private/roles/-components/edit-user-roles-dialog.tsx.stub' => 'src/routes/_private/roles/-components/edit-user-roles-dialog.tsx',
        'routes/_private/roles/page.tsx.stub' => 'src/routes/_private/roles/page.tsx',
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
        return __DIR__ . '/../../Stubs/Frontend/LaravelPermissions';
    }

    public function install(): void
    {
        $root = $this->locator->locate($this->laravelRoot, $this->frontendPath);

        if ($root === null) {
            $this->reportUnresolvedRoot();

            return;
        }

        $this->command->info("Frontend project resolved: {$root}");

        $tokens = [
            'packageManager' => $this->manifest->packageManager($root),
            'dependencyReport' => $this->manifest->dependencyReport($root, self::REQUIRED_DEPENDENCIES),
        ];

        foreach (self::FILES as $stub => $relative) {
            $this->write($root, $stub, $relative, $tokens);
        }

        $this->write($root, self::TODO_FILE . '.stub', self::TODO_FILE, $tokens);

        $this->command->info('Frontend permission helpers and the /roles admin page generated.');

        $this->command->warn('Manual steps (see ' . self::TODO_FILE . '):');
        $this->command->line('  1. Add the i18n keys listed there to src/i18n/locales/en.json.');
        $this->command->line(
            '  2. In ' . self::SIDEBAR_FILE . ', add the Roles link inside <Can permission={PERMISSIONS.manageRoles}>.'
        );
    }

    /**
     * @param array<string, string> $tokens
     */
    private function write(string $root, string $stub, string $relative, array $tokens): void
    {
        $destination = $this->locator->resolveDestination($root, $relative);

        $outcome = $this->stubRenderer->renderTo(self::stubDirectory() . '/' . $stub, $destination, $tokens);

        match ($outcome) {
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
            'No React project found next to the application. Skipping the roles frontend step. '
            . 'Pass --frontend-path=<path> to generate it.'
        );
    }
}
