<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Contracts\SetupReporter;
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

    private const USER_MODEL_PATH = 'src/Users/Domain/Models/User.php';

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
        private readonly SetupReporter $reporter,
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
            $this->reporter->warning(
                "{$userModelClass} not found — logins fail until it exists and extends "
                . class_basename($requiredParentClass) . ' (step 2)'
            );

            return;
        }

        $ancestors = class_parents($userModelClass);

        if ($ancestors !== false && in_array($requiredParentClass, $ancestors, true)) {
            return;
        }

        $this->reporter->warning(
            class_basename($userModelClass) . ' does not extend ' . class_basename($requiredParentClass)
            . ' — logins fail until it does (step 2)'
        );
    }

    private function createAuthFiles(): void
    {
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
            '/Actions/ConsumeRecoveryCodeAction.stub' => 'Domain/Actions/ConsumeRecoveryCodeAction.php',
            '/Actions/VerifyTwoFactorCodeAction.stub' => 'Domain/Actions/VerifyTwoFactorCodeAction.php',
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
            '/Actions/EnableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/EnableTwoFactorAuthenticationAction.php',
            '/Actions/ConfirmTwoFactorAuthenticationAction.stub' => 'Domain/Actions/ConfirmTwoFactorAuthenticationAction.php',
            '/Actions/RegenerateRecoveryCodesAction.stub' => 'Domain/Actions/RegenerateRecoveryCodesAction.php',
            '/DataTransferObjects/TwoFactorEnrollmentDto.stub' => 'Domain/DataTransferObjects/TwoFactorEnrollmentDto.php',
            '/Resources/TwoFactorEnrollmentResource.stub' => 'App/Resources/TwoFactorEnrollmentResource.php',
            '/Resources/TwoFactorRecoveryCodesResource.stub' => 'App/Resources/TwoFactorRecoveryCodesResource.php',
            '/Resources/TwoFactorStatusResource.stub' => 'App/Resources/TwoFactorStatusResource.php',
            '/Requests/EnableTwoFactorAuthenticationRequest.stub' => 'App/Requests/EnableTwoFactorAuthenticationRequest.php',
            '/Requests/ConfirmTwoFactorAuthenticationRequest.stub' => 'App/Requests/ConfirmTwoFactorAuthenticationRequest.php',
            '/Controllers/EnableTwoFactorAuthenticationController.stub' => 'App/Controllers/EnableTwoFactorAuthenticationController.php',
            '/Controllers/ConfirmTwoFactorAuthenticationController.stub' => 'App/Controllers/ConfirmTwoFactorAuthenticationController.php',
            '/Controllers/ShowTwoFactorAuthenticationStatusController.stub' => 'App/Controllers/ShowTwoFactorAuthenticationStatusController.php',
        ];

        foreach ($files as $stub => $destination) {
            $this->copyStub($stubsPath . $stub, "src/Authentication/{$destination}");
        }
    }

    private function copyStub(string $source, string $destinationRelative): void
    {
        $outcome = $this->stubCopier->copy($source, base_path($destinationRelative));

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($destinationRelative),
            StubCopyOutcome::Skipped => $this->reporter->skipped($destinationRelative),
        };
    }

    private function copyMigration(): void
    {
        $stub = __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php';

        $outcome = $this->stubCopier->copy(
            $stub,
            base_path($destination)
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written($destination),
            StubCopyOutcome::Skipped => $this->reporter->skipped($destination),
        };
    }

    private function copyConfigFiles(): void
    {
        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $outcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written('config/google2fa.php'),
            StubCopyOutcome::Skipped => $this->reporter->skipped('config/google2fa.php'),
        };
    }

    private function copyLangFiles(): void
    {
        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        $outcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written('lang/en/google2fa.php'),
            StubCopyOutcome::Skipped => $this->reporter->skipped('lang/en/google2fa.php'),
        };
    }

    private function registerRoutes(): void
    {
        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $routesStubOutcome = $this->stubCopier->copy(
            __DIR__ . '/../../Stubs/Google2FA/routes/two-factor-auth.stub',
            base_path('routes/' . self::ROUTES_FILE_NAME)
        );

        match ($routesStubOutcome) {
            StubCopyOutcome::Written => $this->reporter->written('routes/' . self::ROUTES_FILE_NAME),
            StubCopyOutcome::Skipped => $this->reporter->skipped('routes/' . self::ROUTES_FILE_NAME),
        };

        $outcome = $this->routeFileRegistrar->register(
            base_path(self::API_ROUTES_PATH),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->reporter->written(self::API_ROUTES_PATH),
            RouteRegistrationOutcome::AlreadyRegistered => $this->reporter->skipped(self::API_ROUTES_PATH),
            RouteRegistrationOutcome::ParentMissing => $this->reporter->warning(
                self::API_ROUTES_PATH . " not found — add {$requireStatement} to your API routes"
            ),
            RouteRegistrationOutcome::Failed => $this->reporter->warning(
                'Could not edit ' . self::API_ROUTES_PATH . " — add {$requireStatement} to it yourself"
            ),
            RouteRegistrationOutcome::Corrupted => $this->reporter->error(
                self::API_ROUTES_PATH . " was left inconsistent while adding {$requireStatement} — inspect it"
            ),
        };
    }

    /**
     * The two manual steps left in the whole install: this package cannot
     * edit a login or a User model it did not generate.
     */
    private function writeManualIntegrationGuide(): void
    {
        $outcome = $this->stubRenderer->renderTo(
            __DIR__ . '/../../Stubs/Google2FA/' . self::TODO_FILE . '.stub',
            base_path(self::TODO_FILE),
            [
                'gateConstructorSnippet' => self::GATE_CONSTRUCTOR_SNIPPET,
                'gateCallSnippet' => self::GATE_CALL_SNIPPET,
            ],
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->reporter->written(self::TODO_FILE),
            StubCopyOutcome::Skipped => $this->reporter->skipped(self::TODO_FILE),
        };

        $this->reporter->manualStep('Wire the challenge into `LoginAction`', self::LOGIN_ACTION_PATH, [
            'Inject the challenge action through the constructor:',
            ...explode("\n", self::GATE_CONSTRUCTOR_SNIPPET),
            'Then call it right after "$request->session()->regenerate();", before "return $user;":',
            self::GATE_CALL_SNIPPET,
        ]);

        $this->reporter->manualStep('Make `User` support 2FA', self::USER_MODEL_PATH, [
            self::USER_MODEL_CLASS . ' must extend ' . self::TWO_FACTOR_AUTHENTICATABLE_CLASS
            . ' instead of Illuminate\\Foundation\\Auth\\User.',
        ]);

        $this->reporter->manualStep('Run `php artisan migrate`');

        $this->reporter->manualStep('Pick the mode', '.env', [
            'TWO_FACTOR_AUTHENTICATION_MANDATORY=true (every user sets 2FA up at login) '
            . 'or false (users turn it on from /account/two-factor).',
        ]);
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
            $this->reporter->warning(
                self::LOGIN_ACTION_PATH . ' not found — password login stays single-factor (step 1)'
            );

            return;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'IssueTwoFactorChallengeAction')) {
            return;
        }

        $this->reporter->warning(
            'LoginAction does not call IssueTwoFactorChallengeAction — password login stays single-factor (step 1)'
        );
    }
}
