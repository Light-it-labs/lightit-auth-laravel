<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Exceptions\SetupAbortedException;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubCopyOutcome;
use Lightitlabs\Tools\StubRenderer;

final class Google2FAInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Enums',
        'Authentication/Domain/Exceptions',
        'Authentication/App/Resources',
    ];

    private const PACKAGES = [
        'pragmarx/google2fa-laravel',
        'pragmarx/google2fa-qrcode',
        'bacon/bacon-qr-code',
    ];

    private const ROUTES_LABEL = 'two-factor authentication';

    private const ROUTES_FILE_NAME = 'two-factor-auth.php';

    private const API_ROUTES_PATH = 'routes/api.php';

    private const TODO_FILE = 'AUTH-2FA-TODO.md';

    private const LOGIN_ACTION_PATH = 'src/Authentication/Domain/Actions/LoginAction.php';

    /**
     * The constructor injection a consumer must add to their own login. `LoginAction` lives in
     * the challenge action's namespace, so no `use` line: the app's Pint would delete it.
     */
    private const GATE_CONSTRUCTOR_SNIPPET = <<<'PHP'
        public function __construct(
            private readonly AuthFactory $authFactory,
            private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,
        ) {}
        PHP;

    /**
     * The call to the injected challenge action, pasted right before `return $user;`.
     */
    private const GATE_CALL_SNIPPET = '$this->issueTwoFactorChallengeAction->execute($user);';

    /**
     * The consuming boilerplate's own User model - must extend
     * TWO_FACTOR_AUTHENTICATABLE_CLASS or IssueTwoFactorChallengeAction throws a
     * LogicException on every login instead of ever challenging anyone.
     */
    private const USER_MODEL_CLASS = 'Lightit\\Users\\Domain\\Models\\User';

    private const TWO_FACTOR_AUTHENTICATABLE_CLASS = 'Lightit\\Authentication\\Domain\\TwoFactorAuthenticatable';

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly StubRenderer $stubRenderer = new StubRenderer(),
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar(),
    ) {
    }

    /**
     * @throws SetupAbortedException
     */
    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(self::PACKAGES)) {
            throw new SetupAbortedException('Failed to install ' . implode(', ', self::PACKAGES));
        }

        $this->createAuthFiles();
        $this->warnIfUserModelCannotSupportTwoFactor();
        $this->copyMigration();
        $this->copyConfigFiles();
        $this->copyLangFiles();
        $this->registerRoutes();
        $this->writeManualIntegrationGuide();
        $this->warnIfLoginActionNotWired();

        $this->composerInstaller->printSuccess('Libraries for 2FA installed successfully!');
    }

    /**
     * A warning, not a thrown exception: the rest of the install - config,
     * migration, lang files, routes - should still complete instead of being
     * left half-written over something the consumer fixes after. Must run
     * after `createAuthFiles()`, which is what writes
     * `TwoFactorAuthenticatable.php` in the first place.
     */
    private function warnIfUserModelCannotSupportTwoFactor(
        string $userModelClass = self::USER_MODEL_CLASS,
        string $requiredParentClass = self::TWO_FACTOR_AUTHENTICATABLE_CLASS,
    ): void {
        if (! class_exists($userModelClass)) {
            $this->command->warn(
                "Could not find {$userModelClass}. Two-factor authentication needs this class to exist and "
                . "extend {$requiredParentClass} - without it, IssueTwoFactorChallengeAction throws a "
                . 'LogicException on every login instead of ever challenging anyone.'
            );

            return;
        }

        $ancestors = class_parents($userModelClass);

        if ($ancestors !== false && in_array($requiredParentClass, $ancestors, true)) {
            return;
        }

        $this->command->warn(
            "{$userModelClass} does not extend {$requiredParentClass}. Both 'enabled' and 'mandatory' default "
            . 'to true in config/google2fa.php, but IssueTwoFactorChallengeAction only acts on a '
            . "{$requiredParentClass} instance, so it throws a LogicException on every login instead of ever "
            . 'challenging anyone. Change ' . $userModelClass . ' to extend ' . $requiredParentClass
            . ' (instead of Authenticatable) before your first login - see AUTH-2FA-TODO.md.'
        );
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 6, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        foreach (SharedLoginFiles::FILES as $stub => $destination) {
            $this->copyStub(SharedLoginFiles::stubsPath() . $stub, "src/Authentication/{$destination}");
        }

        $this->copyAuthFiles(__DIR__ . '/../../Stubs/Google2FA/Auth');
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/TwoFactorRateLimiter.stub' => 'Domain/TwoFactorRateLimiter.php',
            '/TwoFactorAttemptLimiter.stub' => 'Domain/TwoFactorAttemptLimiter.php',
            '/Actions/DisableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/DisableTwoFactorAuthenticationAction.php',
            '/Actions/SetupTwoFactorAuthenticationAction.stub' => 'Domain/Actions/SetupTwoFactorAuthenticationAction.php',
            '/Actions/GenerateQRCodeAction.stub' => 'Domain/Actions/GenerateQRCodeAction.php',
            '/Actions/GenerateRecoveryCodesAction.stub' => 'Domain/Actions/GenerateRecoveryCodesAction.php',
            '/Actions/VerifyOtpAction.stub' => 'Domain/Actions/VerifyOtpAction.php',
            '/Actions/VerifyTwoFactorToken.stub' => 'Domain/Actions/VerifyTwoFactorToken.php',
            '/Actions/PasswordValidatorAction.stub' => 'Domain/Actions/PasswordValidatorAction.php',
            '/Actions/CompleteTwoFactorAuthenticationAction.stub' => 'Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            '/Actions/VerifyRecoveryCodeAction.stub' => 'Domain/Actions/VerifyRecoveryCodeAction.php',
            '/DataTransferObjects/TwoFactorSetupDto.stub' => 'Domain/DataTransferObjects/TwoFactorSetupDto.php',
            '/Exceptions/TwoFactorAuthException.stub' => 'Domain/Exceptions/TwoFactorAuthException.php',
            '/Resources/TwoFactorAuthenticationSetUpResource.stub' => 'App/Resources/TwoFactorAuthenticationSetUpResource.php',
            '/Controllers/DisableTwoFactorAuthenticationController.stub' => 'App/Controllers/DisableTwoFactorAuthenticationController.php',
            '/Controllers/SetupTwoFactorAuthenticationController.stub' => 'App/Controllers/SetupTwoFactorAuthenticationController.php',
            '/Controllers/CompleteTwoFactorAuthenticationController.stub' => 'App/Controllers/CompleteTwoFactorAuthenticationController.php',
            '/Controllers/RegenerateRecoveryCodesController.stub' => 'App/Controllers/RegenerateRecoveryCodesController.php',
            '/Controllers/VerifyRecoveryCodeController.stub' => 'App/Controllers/VerifyRecoveryCodeController.php',
            '/Controllers/RequestTwoFactorResetController.stub' => 'App/Controllers/RequestTwoFactorResetController.php',
            '/Controllers/ResetTwoFactorAuthenticationController.stub' => 'App/Controllers/ResetTwoFactorAuthenticationController.php',
            '/Actions/IssueTwoFactorResetTokenAction.stub' => 'Domain/Actions/IssueTwoFactorResetTokenAction.php',
            '/Actions/ResetTwoFactorAuthenticationAction.stub' => 'Domain/Actions/ResetTwoFactorAuthenticationAction.php',
            '/Requests/SetupTwoFactorAuthenticationRequest.stub' => 'App/Requests/SetupTwoFactorAuthenticationRequest.php',
            '/Requests/CompleteTwoFactorAuthenticationRequest.stub' => 'App/Requests/CompleteTwoFactorAuthenticationRequest.php',
            '/Requests/DisableTwoFactorAuthenticationRequest.stub' => 'App/Requests/DisableTwoFactorAuthenticationRequest.php',
            '/Requests/GenerateRecoveryCodesRequest.stub' => 'App/Requests/GenerateRecoveryCodesRequest.php',
            '/Requests/VerifyRecoveryCodeRequest.stub' => 'App/Requests/VerifyRecoveryCodeRequest.php',
            '/Requests/RequestTwoFactorResetRequest.stub' => 'App/Requests/RequestTwoFactorResetRequest.php',
            '/Requests/ResetTwoFactorAuthenticationRequest.stub' => 'App/Requests/ResetTwoFactorAuthenticationRequest.php',
        ];

        foreach ($files as $stub => $destination) {
            $this->copyStub($stubsPath . $stub, "src/Authentication/{$destination}");
        }
    }

    private function copyStub(string $source, string $destinationRelative): void
    {
        $outcome = $this->stubCopier->copy($source, base_path($destinationRelative));

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated("Created: {$destinationRelative}"),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped($destinationRelative),
        };
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(2, 6, 'Copying migration files');

        $stub = __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php';

        $outcome = $this->stubCopier->copy(
            $stub,
            base_path($destination)
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printMigrationCreated("Created: {$destination}"),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped($destination),
        };
    }

    private function copyConfigFiles(): void
    {
        $this->composerInstaller->printStep(3, 6, 'Copying config files');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $outcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printConfigPublished(
                'Config file published: config/google2fa.php'
            ),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('config/google2fa.php'),
        };
    }

    private function copyLangFiles(): void
    {
        $this->composerInstaller->printStep(4, 6, 'Copying lang files');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        $outcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printConfigPublished(
                'Lang file published: lang/en/google2fa.php'
            ),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('lang/en/google2fa.php'),
        };
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(5, 6, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $routesStubOutcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/routes/two-factor-auth.stub',
            base_path('routes/' . self::ROUTES_FILE_NAME)
        );

        match ($routesStubOutcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated(
                'Created: routes/' . self::ROUTES_FILE_NAME
            ),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('routes/' . self::ROUTES_FILE_NAME),
        };

        $outcome = $this->routeFileRegistrar->register(
            base_path(self::API_ROUTES_PATH),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                'Updated ' . self::API_ROUTES_PATH . ": {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Two-factor authentication routes already required in ' . self::API_ROUTES_PATH
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find ' . self::API_ROUTES_PATH . '. '
                . "Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to " . self::API_ROUTES_PATH . ' automatically. '
                . 'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                self::API_ROUTES_PATH . " was left in an inconsistent state while adding {$requireStatement}. "
                . 'Please inspect the file.'
            ),
        };
    }

    /**
     * The two manual steps left in the whole install: this package cannot
     * edit a login or a User model it did not generate.
     */
    private function writeManualIntegrationGuide(): void
    {
        $this->composerInstaller->printStep(6, 6, 'Writing manual integration guide');

        $outcome = $this->stubRenderer->renderTo(
            __DIR__ . '/../../Stubs/Google2FA/' . self::TODO_FILE . '.stub',
            base_path(self::TODO_FILE),
            [
                'gateConstructorSnippet' => self::GATE_CONSTRUCTOR_SNIPPET,
                'gateCallSnippet' => self::GATE_CALL_SNIPPET,
            ],
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated('Created: ' . self::TODO_FILE),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped(self::TODO_FILE),
        };

        $this->command->line('Inject the challenge action into LoginAction via its constructor:');
        $this->printMultilineSnippet(self::GATE_CONSTRUCTOR_SNIPPET);

        $this->command->line(
            'Then call it right after "$request->session()->regenerate();" and before "return $user;":',
        );
        $this->composerInstaller->printBoxedMessage(self::GATE_CALL_SNIPPET);

        $this->command->line(
            self::USER_MODEL_CLASS . ' must extend ' . self::TWO_FACTOR_AUTHENTICATABLE_CLASS
            . ' instead of Illuminate\\Foundation\\Auth\\User - see ' . self::TODO_FILE . '.',
        );
    }

    /**
     * The last-mile check that the manual LoginAction paste actually
     * happened. This package cannot edit a login file it did not generate,
     * so it only reads it (never patches it) and warns when the challenge
     * action still isn't wired in - password login otherwise stays
     * single-factor with no signal that anything is missing.
     */
    private function warnIfLoginActionNotWired(): void
    {
        $path = base_path(self::LOGIN_ACTION_PATH);

        if (! is_file($path)) {
            $this->command->warn(
                'Could not find ' . self::LOGIN_ACTION_PATH . '. Password login stays single-factor until '
                . 'LoginAction injects and calls IssueTwoFactorChallengeAction - see ' . self::TODO_FILE . '.'
            );

            return;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'IssueTwoFactorChallengeAction')) {
            return;
        }

        $this->command->warn(
            self::LOGIN_ACTION_PATH . ' does not reference IssueTwoFactorChallengeAction. Password login stays '
            . 'single-factor until LoginAction injects and calls it - see ' . self::TODO_FILE . '.'
        );
    }

    /**
     * Prints a multi-line snippet one line at a time, so each line reaches
     * the console as its own write - unlike a single multi-line string,
     * this keeps assertions against one specific line of the snippet from
     * colliding with assertions against another line of the same snippet.
     */
    private function printMultilineSnippet(string $snippet): void
    {
        foreach (explode("\n", $snippet) as $line) {
            $this->command->line($line);
        }
    }
}
