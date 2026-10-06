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

final class PasskeysInstaller implements AuthInstallerInterface
{
    public const PACKAGES = ['web-auth/webauthn-lib:^5.3'];

    private const TOTAL_STEPS = 5;

    private const ROUTES_LABEL = 'passkeys';

    private const ROUTES_FILE_NAME = 'passkeys.php';

    private const API_ROUTES_PATH = 'routes/api.php';

    private const TODO_FILE = 'AUTH-PASSKEYS-TODO.md';

    private const MIGRATIONS_DIRECTORY = 'database/migrations';

    private const MIGRATION_NAME = 'create_passkeys_table';

    private const MIGRATION_FILE = self::MIGRATIONS_DIRECTORY . '/2026_10_01_000000_' . self::MIGRATION_NAME . '.php';

    private const CONFIG_FILE = 'config/passkeys.php';

    public const FILES = [
        'PasskeyRateLimiter.stub' => 'Domain/PasskeyRateLimiter.php',
        'PasskeyChallengeStore.stub' => 'Domain/PasskeyChallengeStore.php',
        'Services/PasskeyCeremonyService.stub' => 'Domain/Services/PasskeyCeremonyService.php',
        'Models/Passkey.stub' => 'Domain/Models/Passkey.php',
        'DataTransferObjects/StorePasskeyDto.stub' => 'Domain/DataTransferObjects/StorePasskeyDto.php',
        'DataTransferObjects/VerifiedPasskeyDto.stub' => 'Domain/DataTransferObjects/VerifiedPasskeyDto.php',
        'Exceptions/PasskeyChallengeExpiredException.stub' => 'Domain/Exceptions/PasskeyChallengeExpiredException.php',
        'Exceptions/PasskeyRegistrationFailedException.stub' => 'Domain/Exceptions/PasskeyRegistrationFailedException.php',
        'Exceptions/PasskeyAlreadyRegisteredException.stub' => 'Domain/Exceptions/PasskeyAlreadyRegisteredException.php',
        'Actions/StartPasskeyRegistrationAction.stub' => 'Domain/Actions/StartPasskeyRegistrationAction.php',
        'Actions/StorePasskeyAction.stub' => 'Domain/Actions/StorePasskeyAction.php',
        'Actions/ListPasskeysAction.stub' => 'Domain/Actions/ListPasskeysAction.php',
        'Actions/RenamePasskeyAction.stub' => 'Domain/Actions/RenamePasskeyAction.php',
        'Actions/DeletePasskeyAction.stub' => 'Domain/Actions/DeletePasskeyAction.php',
        'Requests/StartPasskeyRegistrationRequest.stub' => 'App/Requests/StartPasskeyRegistrationRequest.php',
        'Requests/StorePasskeyRequest.stub' => 'App/Requests/StorePasskeyRequest.php',
        'Requests/RenamePasskeyRequest.stub' => 'App/Requests/RenamePasskeyRequest.php',
        'Requests/DeletePasskeyRequest.stub' => 'App/Requests/DeletePasskeyRequest.php',
        'Resources/PasskeyResource.stub' => 'App/Resources/PasskeyResource.php',
        'Resources/PasskeyRegistrationOptionsResource.stub' => 'App/Resources/PasskeyRegistrationOptionsResource.php',
        'Controllers/ListPasskeysController.stub' => 'App/Controllers/ListPasskeysController.php',
        'Controllers/StartPasskeyRegistrationController.stub' => 'App/Controllers/StartPasskeyRegistrationController.php',
        'Controllers/StorePasskeyController.stub' => 'App/Controllers/StorePasskeyController.php',
        'Controllers/RenamePasskeyController.stub' => 'App/Controllers/RenamePasskeyController.php',
        'Controllers/DeletePasskeyController.stub' => 'App/Controllers/DeletePasskeyController.php',
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
        return __DIR__ . '/../../Stubs/Passkeys';
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

        $this->composerInstaller->printSuccess('Passkey registration installed successfully!');
    }

    /**
     * @throws SetupAbortedException
     */
    public function writeFiles(): void
    {
        $this->createAuthFiles();
        $this->copyMigration();
        $this->copyConfigFile();
        $this->registerRoutes();
        $this->writeManualIntegrationGuide();
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, self::TOTAL_STEPS, 'Creating passkey files');

        foreach (self::FILES as $stub => $destination) {
            $this->copy(self::stubDirectory() . '/Auth/' . $stub, "src/Authentication/{$destination}");
        }
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(2, self::TOTAL_STEPS, 'Copying migration files');

        $existing = $this->migrationLocator->find(base_path(self::MIGRATIONS_DIRECTORY), self::MIGRATION_NAME);

        if ($existing !== null) {
            $this->composerInstaller->printSkipped(self::MIGRATIONS_DIRECTORY . "/{$existing}");

            return;
        }

        $this->copy(self::stubDirectory() . '/database/migrations/create_passkeys_table.stub', self::MIGRATION_FILE);
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(3, self::TOTAL_STEPS, 'Copying config files');

        $this->copy(self::stubDirectory() . '/config/passkeys.stub', self::CONFIG_FILE);
    }

    /**
     * @throws SetupAbortedException
     */
    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(4, self::TOTAL_STEPS, 'Registering routes');

        $this->copy(self::stubDirectory() . '/routes/passkeys.stub', 'routes/' . self::ROUTES_FILE_NAME);

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
                'Passkey routes already required in ' . self::API_ROUTES_PATH
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
        $this->composerInstaller->printStep(5, self::TOTAL_STEPS, 'Writing integration guide');

        $outcome = $this->stubRenderer->renderTo(
            self::stubDirectory() . '/' . self::TODO_FILE . '.stub',
            base_path(self::TODO_FILE),
            [],
        );

        $this->report($outcome, self::TODO_FILE);

        $this->command->line(
            'Run php artisan migrate, then set PASSKEYS_RP_ID, PASSKEYS_ALLOWED_ORIGINS and '
            . 'PASSKEYS_USER_HANDLE_SECRET in .env - see '
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
