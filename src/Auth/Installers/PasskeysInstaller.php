<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubRenderer;

final class PasskeysInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Resources',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Exceptions',
        'Authentication/Domain/Models',
        'Authentication/Domain/Services',
    ];

    private const ROUTES_LABEL = 'passkeys';

    private const ROUTES_FILE_NAME = 'passkeys.php';

    private const TODO_FILE = 'AUTH-PASSKEYS-TODO.md';

    /**
     * Patterns a pre-existing `passkeys`/`auth/passkey` route in the consumer's
     * own routes file would match. Detected before appending the generated
     * `require`, because the require is appended at end of file - a route the
     * consumer already declared for the same URI wins, and the generated one
     * silently never fires.
     *
     * @var array<string, string>
     */
    private const SHADOW_PATTERNS = [
        'passkeys' => '/(?:Route::|->)(?:get|post|put|patch|delete)\(\s*[\'"]\/?passkeys/',
        'auth/passkey' => '/(?:Route::|->)(?:get|post|put|patch|delete)\(\s*[\'"]\/?auth\/passkey/',
    ];

    private readonly OriginMarker $originMarker;

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar,
        private readonly string $apiRoutesPath = 'routes/api.php',
        private readonly StubRenderer $stubRenderer = new StubRenderer,
        ?OriginMarker $originMarker = null,
    ) {
        $this->originMarker = $originMarker ?? OriginMarker::resolved();
    }

    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(['web-auth/webauthn-lib:^5.3'])) {
            $this->command->error('Failed to install web-auth/webauthn-lib');

            return;
        }

        $this->createAuthFiles();
        $this->copyConfigFile();
        $this->copyMigration();
        $this->registerRoutes();
        $this->copyTodoDoc();

        $this->composerInstaller->printSuccess('Passkeys (WebAuthn) installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 5, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $this->copyAuthFiles(__DIR__.'/../../Stubs/Passkeys/Auth');
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Models/Passkey.stub' => 'Domain/Models/Passkey.php',
            '/Services/PasskeyCeremonyService.stub' => 'Domain/Services/PasskeyCeremonyService.php',
            '/Exceptions/PasskeyCeremonyFailedException.stub' => 'Domain/Exceptions/PasskeyCeremonyFailedException.php',
            '/DataTransferObjects/StorePasskeyDto.stub' => 'Domain/DataTransferObjects/StorePasskeyDto.php',
            '/DataTransferObjects/PasskeyLoginDto.stub' => 'Domain/DataTransferObjects/PasskeyLoginDto.php',
            '/DataTransferObjects/PasskeyLoginOptionsDto.stub' => 'Domain/DataTransferObjects/PasskeyLoginOptionsDto.php',
            '/Actions/StartPasskeyRegistrationAction.stub' => 'Domain/Actions/StartPasskeyRegistrationAction.php',
            '/Actions/CompletePasskeyRegistrationAction.stub' => 'Domain/Actions/CompletePasskeyRegistrationAction.php',
            '/Actions/StartPasskeyLoginAction.stub' => 'Domain/Actions/StartPasskeyLoginAction.php',
            '/Actions/PasskeyLoginAction.stub' => 'Domain/Actions/PasskeyLoginAction.php',
            '/Actions/ListPasskeysAction.stub' => 'Domain/Actions/ListPasskeysAction.php',
            '/Actions/DeletePasskeyAction.stub' => 'Domain/Actions/DeletePasskeyAction.php',
            '/Requests/StorePasskeyRequest.stub' => 'App/Requests/StorePasskeyRequest.php',
            '/Requests/PasskeyLoginRequest.stub' => 'App/Requests/PasskeyLoginRequest.php',
            '/Requests/DeletePasskeyRequest.stub' => 'App/Requests/DeletePasskeyRequest.php',
            '/Resources/PasskeyResource.stub' => 'App/Resources/PasskeyResource.php',
            '/Resources/PasskeyCreationOptionsResource.stub' => 'App/Resources/PasskeyCreationOptionsResource.php',
            '/Resources/PasskeyRequestOptionsResource.stub' => 'App/Resources/PasskeyRequestOptionsResource.php',
            '/Controllers/StartPasskeyRegistrationController.stub' => 'App/Controllers/StartPasskeyRegistrationController.php',
            '/Controllers/StorePasskeyController.stub' => 'App/Controllers/StorePasskeyController.php',
            '/Controllers/StartPasskeyLoginController.stub' => 'App/Controllers/StartPasskeyLoginController.php',
            '/Controllers/PasskeyLoginController.stub' => 'App/Controllers/PasskeyLoginController.php',
            '/Controllers/ListPasskeysController.stub' => 'App/Controllers/ListPasskeysController.php',
            '/Controllers/DeletePasskeyController.stub' => 'App/Controllers/DeletePasskeyController.php',
        ];

        foreach ($files as $stub => $destination) {
            $this->writeStubOrFail(
                $stubsPath.$stub,
                base_path("src/Authentication/{$destination}"),
                "src/Authentication/{$destination}"
            );
        }
    }

    private function writeStubOrFail(string $source, string $destination, string $label): void
    {
        $this->stubCopier->copy($source, $destination);

        $this->composerInstaller->printFileCreated("Created: {$label}");
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(2, 5, 'Copying config file');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $this->stubCopier->copy(
            __DIR__.'/../../Stubs/Passkeys/config/passkeys.stub',
            config_path('passkeys.php')
        );
        $this->composerInstaller->printConfigPublished('Config file published: config/passkeys.php');
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, 5, 'Copying migration files');

        if (glob(base_path('database/migrations/*_create_passkeys_table.php')) !== []) {
            $this->composerInstaller->printMigrationCreated(
                'Skipped: a create_passkeys_table migration already exists.'
            );

            return;
        }

        $stub = __DIR__.'/../../Stubs/Passkeys/database/migrations/create_passkeys_table.stub';
        $timestamp = date('Y_m_d_His');
        $destination = "database/migrations/{$timestamp}_create_passkeys_table.php";

        $this->stubCopier->copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(4, 5, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/Passkeys/routes/passkeys.stub',
            base_path('routes/'.self::ROUTES_FILE_NAME),
            'routes/'.self::ROUTES_FILE_NAME
        );

        foreach ($this->routeFileRegistrar->shadowedRoutes(base_path($this->apiRoutesPath), self::SHADOW_PATTERNS) as $shadowed) {
            $this->command->warn(
                "A route matching '{$shadowed}' already exists in {$this->apiRoutesPath}. "
                .'Since the generated require is appended at the end of the file, the existing '
                .'route will take precedence and the generated passkey route will never fire.'
            );
        }

        $outcome = $this->routeFileRegistrar->register(
            base_path($this->apiRoutesPath),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                "Updated {$this->apiRoutesPath}: {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                "Passkeys routes already required in {$this->apiRoutesPath}"
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                "Could not find {$this->apiRoutesPath}. "
                ."Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to {$this->apiRoutesPath} automatically. "
                .'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                "{$this->apiRoutesPath} was left in an inconsistent state while adding {$requireStatement}. "
                .'Please inspect the file.'
            ),
        };
    }

    private function copyTodoDoc(): void
    {
        $this->composerInstaller->printStep(5, 5, 'Copying manual-steps TODO');

        $destination = base_path(self::TODO_FILE);

        if (file_exists($destination)) {
            $this->command->warn('Overwriting: '.self::TODO_FILE);
        }

        $this->stubRenderer->renderTo(
            __DIR__.'/../../Stubs/Passkeys/'.self::TODO_FILE.'.stub',
            $destination,
            [],
            $this->originMarker,
        );
        $this->composerInstaller->printFileCreated('Created: '.self::TODO_FILE);
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

        $this->writeStubOrFail($source, $destination, $label);
    }
}
