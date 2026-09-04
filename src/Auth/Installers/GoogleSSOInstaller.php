<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\FileManipulator;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;

final class GoogleSSOInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
    ];

    private const ROUTES_LABEL = 'google sso';

    private const ROUTES_FILE_NAME = 'google-sso.php';

    private const GOOGLE_CLIENT_SERVICE_PROVIDER = 'App\Providers\GoogleClientServiceProvider';

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly FileManipulator $fileManipulator,
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar,
    ) {}

    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(['google/apiclient'])) {
            $this->command->error('Failed to install google/apiclient');

            return;
        }

        $this->createAuthFiles();
        $this->copySharedFiles();
        $this->registerRoutes();
        $this->registerServiceProvider();

        $this->composerInstaller->printSuccess('Client library for Google APIs installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 4, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__.'/../../Stubs/GoogleSSO/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Requests/GoogleLoginRequest.stub' => 'App/Requests/GoogleLoginRequest.php',
            '/Controllers/GoogleLoginController.stub' => 'App/Controllers/GoogleLoginController.php',
            '/Actions/GoogleLoginAction.stub' => 'Domain/Actions/GoogleLoginAction.php',
        ];

        foreach ($files as $stub => $destination) {
            $this->stubCopier->copy(
                $stubsPath.$stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }

    private function copySharedFiles(): void
    {
        $this->composerInstaller->printStep(2, 4, 'Creating shared exception file');

        $sharedStubPath = __DIR__.'/../../Stubs/Exceptions/InvalidGoogleTokenException.stub';
        $sharedDestPath = base_path('src/Shared/App/Exceptions/Http/InvalidGoogleTokenException.php');

        $sharedDir = dirname($sharedDestPath);

        if (! is_dir($sharedDir)) {
            mkdir($sharedDir, 0755, true);
        }

        $this->stubCopier->copy($sharedStubPath, $sharedDestPath);
        $this->composerInstaller->printFileCreated(
            'Created: src/Shared/App/Exceptions/Http/InvalidGoogleTokenException.php'
        );
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(3, 4, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/GoogleSSO/routes/google-sso.stub',
            base_path('routes/'.self::ROUTES_FILE_NAME),
            'routes/'.self::ROUTES_FILE_NAME
        );

        $outcome = $this->routeFileRegistrar->register(
            base_path('routes/api.php'),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                "Updated routes/api.php: {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Google SSO routes already required in routes/api.php'
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find routes/api.php. '
                ."Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to routes/api.php automatically. "
                .'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                "routes/api.php was left in an inconsistent state while adding {$requireStatement}. "
                .'Please inspect the file.'
            ),
        };
    }

    private function registerServiceProvider(): void
    {
        $this->composerInstaller->printStep(4, 4, 'Registering Google client service provider');

        $this->copyServiceProviderFiles();

        $providersPath = base_path('bootstrap/providers.php');

        if (! file_exists($providersPath)) {
            $this->command->warn(
                "Could not find {$providersPath}. "
                .'Please register '.self::GOOGLE_CLIENT_SERVICE_PROVIDER.' manually.'
            );

            return;
        }

        $contents = (string) file_get_contents($providersPath);

        if (str_contains($contents, self::GOOGLE_CLIENT_SERVICE_PROVIDER.'::class')) {
            $this->composerInstaller->printFileCreated(
                self::GOOGLE_CLIENT_SERVICE_PROVIDER.' already registered in bootstrap/providers.php'
            );

            return;
        }

        $this->fileManipulator->replaceInFile(
            'return [',
            'return ['.PHP_EOL.'    \\'.self::GOOGLE_CLIENT_SERVICE_PROVIDER.'::class,',
            $providersPath
        );
        $this->composerInstaller->printFileCreated(
            'Updated bootstrap/providers.php: registered '.self::GOOGLE_CLIENT_SERVICE_PROVIDER
        );
    }

    private function copyServiceProviderFiles(): void
    {
        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/GoogleSSO/config/google-sso.stub',
            config_path('google-sso.php'),
            'config/google-sso.php'
        );

        if (! is_dir(app_path('Providers'))) {
            mkdir(app_path('Providers'), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/GoogleSSO/Providers/GoogleClientServiceProvider.stub',
            app_path('Providers/GoogleClientServiceProvider.php'),
            'app/Providers/GoogleClientServiceProvider.php'
        );
    }

    /**
     * Skip-and-report if the destination already exists; throw if the source stub is
     * missing or the copy fails.
     */
    private function writeStubIfMissing(string $source, string $destination, string $label): void
    {
        if (file_exists($destination)) {
            $this->composerInstaller->printFileCreated("Skipped {$label}: the file already exists.");

            return;
        }

        $this->stubCopier->copy($source, $destination);
        $this->composerInstaller->printFileCreated("Created: {$label}");
    }
}
