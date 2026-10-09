<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\MigrationLocator;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

final class SocialLoginInstaller implements AuthInstallerInterface
{
    public const PACKAGES = ['firebase/php-jwt:^7.0'];

    private const TOTAL_STEPS = 6;

    private const ROUTES_LABEL = 'social login';

    private const ROUTES_FILE_NAME = 'social-login.php';

    private const API_ROUTES_PATH = 'routes/api.php';

    private const TODO_FILE = 'AUTH-SOCIAL-TODO.md';

    private const MIGRATIONS_DIRECTORY = 'database/migrations';

    private const MIGRATION_NAME = 'create_social_accounts_table';

    private const MIGRATION_FILE = self::MIGRATIONS_DIRECTORY . '/2026_10_05_000000_' . self::MIGRATION_NAME . '.php';

    private const CONFIG_FILE = 'config/social-login.php';

    public const FILES = [
        'Contracts/SocialProvider.stub' => 'Domain/Contracts/SocialProvider.php',
        'DataTransferObjects/SocialIdentityDto.stub' => 'Domain/DataTransferObjects/SocialIdentityDto.php',
        'DataTransferObjects/SocialLoginDto.stub' => 'Domain/DataTransferObjects/SocialLoginDto.php',
        'Exceptions/InvalidSocialTokenException.stub' => 'Domain/Exceptions/InvalidSocialTokenException.php',
        'Exceptions/SocialEmailNotVerifiedException.stub' => 'Domain/Exceptions/SocialEmailNotVerifiedException.php',
        'Exceptions/SocialProviderUnknownException.stub' => 'Domain/Exceptions/SocialProviderUnknownException.php',
        'Exceptions/SocialProviderUnavailableException.stub' => 'Domain/Exceptions/SocialProviderUnavailableException.php',
        'Models/SocialAccount.stub' => 'Domain/Models/SocialAccount.php',
        'SocialProviders/GoogleSigningKeys.stub' => 'Domain/SocialProviders/GoogleSigningKeys.php',
        'SocialProviders/GoogleProvider.stub' => 'Domain/SocialProviders/GoogleProvider.php',
        'SocialProviderRegistry.stub' => 'Domain/SocialProviderRegistry.php',
        'SocialLoginRateLimiter.stub' => 'Domain/SocialLoginRateLimiter.php',
        'Actions/SocialLoginAction.stub' => 'Domain/Actions/SocialLoginAction.php',
        'Requests/SocialLoginRequest.stub' => 'App/Requests/SocialLoginRequest.php',
        'Controllers/SocialLoginController.stub' => 'App/Controllers/SocialLoginController.php',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly StubRenderer $stubRenderer = new StubRenderer(),
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar(),
        private readonly MigrationLocator $migrationLocator = new MigrationLocator(),
    ) {
    }

    public static function stubDirectory(): string
    {
        return __DIR__ . '/../../Stubs/SocialLogin';
    }

    /**
     * @throws SetupAbortedException
     */
    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(self::PACKAGES)) {
            throw new SetupAbortedException('Failed to install ' . implode(', ', self::PACKAGES));
        }

        $this->writeFiles();

        $this->composerInstaller->printSuccess('Social login installed successfully!');
    }

    /**
     * @throws SetupAbortedException
     */
    public function writeFiles(): void
    {
        $this->createAuthFiles();
        $this->copySharedLoginFiles();
        $this->copyMigration();
        $this->copyConfigFile();
        $this->registerRoutes();
        $this->writeManualIntegrationGuide();
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, self::TOTAL_STEPS, 'Creating social login files');

        foreach (self::FILES as $stub => $destination) {
            $this->copy(self::stubDirectory() . '/Auth/' . $stub, "src/Authentication/{$destination}");
        }
    }

    private function copySharedLoginFiles(): void
    {
        $this->composerInstaller->printStep(2, self::TOTAL_STEPS, 'Creating shared login primitives');

        foreach (SharedLoginFiles::FILES as $stub => $destination) {
            $this->copy(SharedLoginFiles::stubsPath() . $stub, "src/Authentication/{$destination}");
        }
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, self::TOTAL_STEPS, 'Copying migration files');

        $existing = $this->migrationLocator->find(base_path(self::MIGRATIONS_DIRECTORY), self::MIGRATION_NAME);

        if ($existing !== null) {
            $this->composerInstaller->printSkipped(self::MIGRATIONS_DIRECTORY . "/{$existing}");

            return;
        }

        $this->copy(
            self::stubDirectory() . '/database/migrations/create_social_accounts_table.stub',
            self::MIGRATION_FILE,
        );
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(4, self::TOTAL_STEPS, 'Copying config files');

        $this->copy(self::stubDirectory() . '/config/social-login.stub', self::CONFIG_FILE);
    }

    /**
     * @throws SetupAbortedException
     */
    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(5, self::TOTAL_STEPS, 'Registering routes');

        $this->copy(self::stubDirectory() . '/routes/social-login.stub', 'routes/' . self::ROUTES_FILE_NAME);

        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($this->routeFileRegistrar->register(
            base_path(self::API_ROUTES_PATH),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL,
        )) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                'Updated ' . self::API_ROUTES_PATH . ": {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Social login routes already required in ' . self::API_ROUTES_PATH
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find ' . self::API_ROUTES_PATH . ". Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to " . self::API_ROUTES_PATH . ' automatically. Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => throw SetupAbortedException::corruptedRouteFile(
                self::API_ROUTES_PATH,
                $requireStatement,
            ),
        };
    }

    private function writeManualIntegrationGuide(): void
    {
        $this->composerInstaller->printStep(6, self::TOTAL_STEPS, 'Writing integration guide');

        $outcome = $this->stubRenderer->renderTo(
            self::stubDirectory() . '/' . self::TODO_FILE . '.stub',
            base_path(self::TODO_FILE),
            [],
        );

        $this->report($outcome, self::TODO_FILE);

        $this->command->line(
            'Run php artisan migrate, then create a Google OAuth client and set GOOGLE_CLIENT_ID in .env - see '
            . self::TODO_FILE . '.'
        );
    }

    private function copy(string $source, string $destinationRelative): void
    {
        $destination = base_path($destinationRelative);
        $directory = \dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->report($this->stubCopier->copy($source, $destination), $destinationRelative);
    }

    private function report(StubCopyOutcome $outcome, string $destinationRelative): void
    {
        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated("Created: {$destinationRelative}"),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped($destinationRelative),
        };
    }
}
