<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
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

    private const ROUTES_LABEL = 'two-factor authentication';

    private const ROUTES_FILE_NAME = 'two-factor-auth.php';

    private const API_ROUTES_PATH = 'routes/api.php';

    private const TODO_FILE = 'AUTH-2FA-TODO.md';

    /**
     * The single line a consumer must paste into their own login - this
     * package cannot write to a login it does not generate. Printed at the
     * end of install() and mirrored, word for word, into AUTH-2FA-TODO.md
     * via the `gateSnippet` token so the two never drift apart.
     */
    private const GATE_SNIPPET = 'app(\\Lightit\\Authentication\\Domain\\Actions\\TwoFactorLoginGate::class)->guardAgainstChallenge($user);';

    /**
     * The second manual step: this package cannot write to a ServiceProvider
     * it did not generate either, so registering the `2fa` rate limiter the
     * `throttle:2fa` middleware needs (see TwoFactorRateLimiter.stub and
     * routes/two-factor-auth.stub) is left to the consumer, same as the gate
     * snippet above.
     */
    private const RATE_LIMITER_SNIPPET = 'Lightit\\Authentication\\Domain\\TwoFactorRateLimiter::register();';

    /**
     * The consuming boilerplate's own User model - the same FQCN every
     * generated 2FA stub already assumes (see TwoFactorLoginGate.stub,
     * LoginByUserAction.stub). Those stubs call methods that only exist on
     * TwoFactorAuthenticatable - if this class doesn't extend it, every
     * login throws BadMethodCallException the moment 2FA is wired in.
     */
    private const USER_MODEL_CLASS = 'Lightit\\Users\\Domain\\Models\\User';

    private const TWO_FACTOR_AUTHENTICATABLE_CLASS = 'Lightit\\Authentication\\Domain\\TwoFactorAuthenticatable';

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly StubRenderer $stubRenderer = new StubRenderer,
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar,
    ) {}

    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages([
            'pragmarx/google2fa-laravel',
            'pragmarx/google2fa-qrcode',
            'bacon/bacon-qr-code',
        ])) {
            $this->command->error('Installing Google 2FA laravel and QR Code');

            return;
        }

        $this->createAuthFiles();
        $this->warnIfUserModelCannotSupportTwoFactor();
        $this->publishConfiguration();
        $this->copyMigration();
        $this->copyConfigFiles();
        $this->copyLangFiles();
        $this->registerRoutes();
        $this->writeManualIntegrationGuide();

        $this->composerInstaller->printSuccess('Libraries for 2FA installed successfully!');
    }

    /**
     * `config/google2fa.php`'s `enabled` and `mandatory` both default to
     * `true`, so `TwoFactorLoginGate::guardAgainstChallenge()` - which the
     * consumer wires into their own login by hand, per AUTH-2FA-TODO.md -
     * calls `TwoFactorAuthenticatable`-only methods on the very next login,
     * with no config change required to hit it. There is nothing safe to
     * auto-patch here: unlike the stubs this package generates, this is the
     * consumer's already-customized User model, and rewriting its `extends`
     * clause would be a much heavier, riskier edit than the one-line gate
     * call it needs.
     *
     * Must run after `createAuthFiles()`: that's what writes
     * `TwoFactorAuthenticatable.php` in the first place, so checking before
     * it exists can never pass. A warning, not a thrown exception, for the
     * same reason AUTH-2FA-TODO.md treats this as a normal follow-up step:
     * the rest of the install - config, migration, lang files, routes -
     * should still complete instead of being left half-written over
     * something the consumer fixes after.
     */
    private function warnIfUserModelCannotSupportTwoFactor(
        string $userModelClass = self::USER_MODEL_CLASS,
        string $requiredParentClass = self::TWO_FACTOR_AUTHENTICATABLE_CLASS,
    ): void {
        if (! class_exists($userModelClass)) {
            $this->command->warn(
                "Could not find {$userModelClass}. Two-factor authentication needs this class to exist and "
                ."extend {$requiredParentClass} - without it, every login throws BadMethodCallException as "
                .'soon as 2FA is wired in.'
            );

            return;
        }

        $ancestors = class_parents($userModelClass);

        if ($ancestors !== false && in_array($requiredParentClass, $ancestors, true)) {
            return;
        }

        $this->command->warn(
            "{$userModelClass} does not extend {$requiredParentClass}. Both 'enabled' and 'mandatory' default "
            ."to true in config/google2fa.php, so every login calls {$requiredParentClass}-only methods on "
            .'this class and throws BadMethodCallException. Change '.$userModelClass.' to extend '
            .$requiredParentClass.' (instead of Authenticatable) before your first login - see AUTH-2FA-TODO.md.'
        );
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 7, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $sharedStubsPath = __DIR__.'/../../Stubs/Shared/Auth';
        $sharedFiles = [
            '/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
        ];

        foreach ($sharedFiles as $stub => $destination) {
            $this->copyStub($sharedStubsPath.$stub, "src/Authentication/{$destination}");
        }

        $this->copyAuthFiles(__DIR__.'/../../Stubs/Google2FA/Auth');
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
            '/TwoFactorRateLimiter.stub' => 'Domain/TwoFactorRateLimiter.php',
            '/Actions/DisableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/DisableTwoFactorAuthenticationAction.php',
            '/Actions/SetupTwoFactorAuthenticationAction.stub' => 'Domain/Actions/SetupTwoFactorAuthenticationAction.php',
            '/Actions/GenerateQRCodeAction.stub' => 'Domain/Actions/GenerateQRCodeAction.php',
            '/Actions/GenerateRecoveryCodesAction.stub' => 'Domain/Actions/GenerateRecoveryCodesAction.php',
            '/Actions/VerifyOtpAction.stub' => 'Domain/Actions/VerifyOtpAction.php',
            '/Actions/VerifyTwoFactorToken.stub' => 'Domain/Actions/VerifyTwoFactorToken.php',
            '/Actions/PasswordValidatorAction.stub' => 'Domain/Actions/PasswordValidatorAction.php',
            '/Actions/CompleteTwoFactorAuthenticationAction.stub' => 'Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            '/Actions/VerifyRecoveryCodeAction.stub' => 'Domain/Actions/VerifyRecoveryCodeAction.php',
            '/Actions/TwoFactorLoginGate.stub' => 'Domain/Actions/TwoFactorLoginGate.php',
            '/DataTransferObjects/TwoFactorSetupDto.stub' => 'Domain/DataTransferObjects/TwoFactorSetupDto.php',
            '/DataTransferObjects/TwoFactorTokenPayloadDto.stub' => 'Domain/DataTransferObjects/TwoFactorTokenPayloadDto.php',
            '/Enums/TwoFactorReason.stub' => 'Domain/Enums/TwoFactorReason.php',
            '/Exceptions/TwoFactorAuthException.stub' => 'Domain/Exceptions/TwoFactorAuthException.php',
            '/Exceptions/TwoFactorChallengeException.stub' => 'Domain/Exceptions/TwoFactorChallengeException.php',
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
            $this->copyStub($stubsPath.$stub, "src/Authentication/{$destination}");
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

    private function publishConfiguration(): void
    {
        $this->composerInstaller->printStep(2, 7, 'Publishing configuration');

        $this->command->call('vendor:publish', [
            '--provider' => 'PragmaRX\Google2FALaravel\ServiceProvider',
        ]);
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, 7, 'Copying migration files');

        $stub = __DIR__.'/../../../database/migrations/add_two_factor_authentication_columns.stub';
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
        $this->composerInstaller->printStep(4, 7, 'Copying config files');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $outcome = $this->stubCopier->copy(
            __DIR__.'/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printConfigPublished('Config file published: config/google2fa.php'),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('config/google2fa.php'),
        };
    }

    private function copyLangFiles(): void
    {
        $this->composerInstaller->printStep(5, 7, 'Copying lang files');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        $outcome = $this->stubCopier->copy(
            __DIR__.'/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printConfigPublished('Lang file published: lang/en/google2fa.php'),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('lang/en/google2fa.php'),
        };
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(6, 7, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $routesStubOutcome = $this->stubCopier->copy(
            __DIR__.'/../../Stubs/Google2FA/routes/two-factor-auth.stub',
            base_path('routes/'.self::ROUTES_FILE_NAME)
        );

        match ($routesStubOutcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated('Created: routes/'.self::ROUTES_FILE_NAME),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped('routes/'.self::ROUTES_FILE_NAME),
        };

        $outcome = $this->routeFileRegistrar->register(
            base_path(self::API_ROUTES_PATH),
            self::ROUTES_FILE_NAME,
            self::ROUTES_LABEL
        );
        $requireStatement = $this->routeFileRegistrar->requireStatement(self::ROUTES_FILE_NAME);

        match ($outcome) {
            RouteRegistrationOutcome::Registered => $this->composerInstaller->printFileCreated(
                'Updated '.self::API_ROUTES_PATH.": {$requireStatement}"
            ),
            RouteRegistrationOutcome::AlreadyRegistered => $this->composerInstaller->printFileCreated(
                'Two-factor authentication routes already required in '.self::API_ROUTES_PATH
            ),
            RouteRegistrationOutcome::ParentMissing => $this->command->warn(
                'Could not find '.self::API_ROUTES_PATH.'. '
                ."Please add {$requireStatement} to your API route file manually."
            ),
            RouteRegistrationOutcome::Failed => $this->command->warn(
                "Could not append {$requireStatement} to ".self::API_ROUTES_PATH.' automatically. '
                .'Please add it manually.'
            ),
            RouteRegistrationOutcome::Corrupted => $this->command->error(
                self::API_ROUTES_PATH." was left in an inconsistent state while adding {$requireStatement}. "
                .'Please inspect the file.'
            ),
        };
    }

    /**
     * The two manual steps left in the whole install: this package cannot
     * edit a login or a ServiceProvider it did not generate, so it prints
     * the exact lines to paste into LoginAction::execute() and
     * AppServiceProvider::boot(), and writes the same lines into
     * AUTH-2FA-TODO.md for later reference.
     */
    private function writeManualIntegrationGuide(): void
    {
        $this->composerInstaller->printStep(7, 7, 'Writing manual integration guide');

        $outcome = $this->stubRenderer->renderTo(
            __DIR__.'/../../Stubs/Google2FA/'.self::TODO_FILE.'.stub',
            base_path(self::TODO_FILE),
            [
                'gateSnippet' => self::GATE_SNIPPET,
                'rateLimiterSnippet' => self::RATE_LIMITER_SNIPPET,
            ],
        );

        match ($outcome) {
            StubCopyOutcome::Written => $this->composerInstaller->printFileCreated('Created: '.self::TODO_FILE),
            StubCopyOutcome::Skipped => $this->composerInstaller->printSkipped(self::TODO_FILE),
        };

        $this->command->line(
            'Paste this line into LoginAction::execute(), right after "$request->session()->regenerate();" and before "return $user;":',
        );
        $this->composerInstaller->printBoxedMessage(self::GATE_SNIPPET);

        $this->command->line('Paste this line into AppServiceProvider::boot(), to register the 2FA rate limiter:');
        $this->composerInstaller->printBoxedMessage(self::RATE_LIMITER_SNIPPET);
    }
}
