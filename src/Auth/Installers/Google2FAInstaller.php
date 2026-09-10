<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\LoginActionPatcher;
use Lightitlabs\Tools\LoginActionPatchOutcome;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use Lightitlabs\Tools\StubCopier;

final class Google2FAInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/Domain/Actions',
        'Authentication/Domain/Actions/Pipes',
        'Authentication/Domain/DataTransferObjects',
        'Authentication/Domain/Enums',
        'Authentication/Domain/Exceptions',
        'Authentication/App/Resources',
    ];

    private const ROUTES_LABEL = 'two-factor authentication';

    private const ROUTES_FILE_NAME = 'two-factor-auth.php';

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
        private readonly RouteFileRegistrar $routeFileRegistrar = new RouteFileRegistrar,
        private readonly LoginActionPatcher $loginActionPatcher = new LoginActionPatcher,
        private readonly string $apiRoutesPath = 'routes/api.php',
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
        $this->publishConfiguration();
        $this->copyMigration();
        $this->copyConfigFiles();
        $this->copyLangFiles();
        $this->registerRoutes();

        $this->composerInstaller->printSuccess('Libraries for 2FA installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 6, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $this->installLoginAction();
        $this->copyAuthFiles(__DIR__.'/../../Stubs/Google2FA/Auth');
    }

    /**
     * `LoginAction.php` is written by both `SanctumInstaller` (a plain
     * `guard()->attempt()`) and this installer (the 2FA pipeline) - on an
     * ordinary `auth:setup` run, Sanctum's plain version is what's on disk by
     * the time this runs, and it must be replaced with the pipeline version.
     * Handled separately from the generic `copyAuthFiles()` loop below because
     * that loop only knows "create if missing", not "replace this specific
     * sibling installer's output but nothing a consumer touched" - see
     * `LoginActionPatcher`.
     */
    private function installLoginAction(): void
    {
        $label = 'src/Authentication/Domain/Actions/LoginAction.php';

        $outcome = $this->loginActionPatcher->install(
            $this->stubCopier,
            __DIR__.'/../../Stubs/Sanctum/Auth/Actions/LoginAction.stub',
            __DIR__.'/../../Stubs/Google2FA/Auth/Actions/LoginAction.stub',
            base_path($label),
        );

        match ($outcome) {
            LoginActionPatchOutcome::Installed => $this->composerInstaller->printFileCreated("Created: {$label}"),
            LoginActionPatchOutcome::Patched => $this->composerInstaller->printFileCreated(
                "Replaced {$label} with the 2FA login pipeline."
            ),
            LoginActionPatchOutcome::AlreadyApplied => $this->composerInstaller->printFileCreated(
                "{$label} already carries the 2FA login pipeline."
            ),
            LoginActionPatchOutcome::CustomizedSkipped => $this->command->warn(
                "Skipped {$label}: it no longer matches this package's generated output, so it looks "
                .'edited. Wire the 2FA pipeline into it by hand - see '
                .'vendor/light-it-labs/lightit-auth-laravel/src/Stubs/Google2FA/Auth/Actions/LoginAction.stub '
                .'for the reference implementation.'
            ),
            LoginActionPatchOutcome::SourceMissing,
            LoginActionPatchOutcome::Failed => $this->command->error(
                "Could not install {$label} ({$outcome->name})."
            ),
        };
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
            '/Actions/CompleteTwoFactorAuthenticationAction.stub' => 'Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            '/Actions/VerifyRecoveryCodeAction.stub' => 'Domain/Actions/VerifyRecoveryCodeAction.php',
            '/Actions/Pipes/IssueSessionMarkerIfNoFinalToken.stub' => 'Domain/Actions/Pipes/IssueSessionMarkerIfNoFinalToken.php',
            '/Actions/DisableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/DisableTwoFactorAuthenticationAction.php',
            '/Actions/SetupTwoFactorAuthenticationAction.stub' => 'Domain/Actions/SetupTwoFactorAuthenticationAction.php',
            '/Actions/GenerateQRCodeAction.stub' => 'Domain/Actions/GenerateQRCodeAction.php',
            '/Actions/GenerateRecoveryCodesAction.stub' => 'Domain/Actions/GenerateRecoveryCodesAction.php',
            '/Actions/VerifyOtpAction.stub' => 'Domain/Actions/VerifyOtpAction.php',
            '/Actions/VerifyTwoFactorToken.stub' => 'Domain/Actions/VerifyTwoFactorToken.php',
            '/Actions/PasswordValidatorAction.stub' => 'Domain/Actions/PasswordValidatorAction.php',
            '/Actions/Pipes/LoginContext.stub' => 'Domain/Actions/Pipes/LoginContext.php',
            '/Actions/Pipes/ValidateCredentials.stub' => 'Domain/Actions/Pipes/ValidateCredentials.php',
            '/Actions/Pipes/ResolveUser.stub' => 'Domain/Actions/Pipes/ResolveUser.php',
            '/Actions/Pipes/BuildLoginResult.stub' => 'Domain/Actions/Pipes/BuildLoginResult.php',
            '/Actions/Pipes/IssueTwoFactorSetupTokenIfMandatory.stub' => 'Domain/Actions/Pipes/IssueTwoFactorSetupTokenIfMandatory.php',
            '/Actions/Pipes/IssueTwoFactorChallengeTokenIfEnabled.stub' => 'Domain/Actions/Pipes/IssueTwoFactorChallengeTokenIfEnabled.php',
            '/DataTransferObjects/TwoFactorSetupDto.stub' => 'Domain/DataTransferObjects/TwoFactorSetupDto.php',
            '/DataTransferObjects/TwoFactorTokenPayloadDto.stub' => 'Domain/DataTransferObjects/TwoFactorTokenPayloadDto.php',
            '/DataTransferObjects/VerifyRecoveryCodeDto.stub' => 'Domain/DataTransferObjects/VerifyRecoveryCodeDto.php',
            '/Enums/TwoFactorReason.stub' => 'Domain/Enums/TwoFactorReason.php',
            '/Exceptions/TwoFactorAuthException.stub' => 'Domain/Exceptions/TwoFactorAuthException.php',
            '/Resources/TwoFactorAuthenticationSetUpResource.stub' => 'App/Resources/TwoFactorAuthenticationSetUpResource.php',
            '/Resources/VerifyRecoveryCodeResource.stub' => 'App/Resources/VerifyRecoveryCodeResource.php',
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
            $this->writeStubIfMissing(
                $stubsPath.$stub,
                base_path("src/Authentication/{$destination}"),
                "src/Authentication/{$destination}"
            );
        }
    }

    private function publishConfiguration(): void
    {
        $this->composerInstaller->printStep(2, 6, 'Publishing configuration');

        $this->command->call('vendor:publish', [
            '--provider' => 'PragmaRX\Google2FALaravel\ServiceProvider',
        ]);
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(3, 6, 'Copying migration files');

        $stub = __DIR__.'/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php';

        if (file_exists(base_path($destination))) {
            $this->composerInstaller->printMigrationCreated("Skipped {$destination}: the file already exists.");

            return;
        }

        $this->stubCopier->copy($stub, base_path($destination));
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function copyConfigFiles(): void
    {
        $this->composerInstaller->printStep(4, 6, 'Copying config files');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $destination = config_path('google2fa.php');

        if (file_exists($destination)) {
            $this->composerInstaller->printConfigPublished('Skipped config/google2fa.php: the file already exists.');

            return;
        }

        $this->stubCopier->copy(__DIR__.'/../../Stubs/Google2FA/config/google2fa.stub', $destination);
        $this->composerInstaller->printConfigPublished('Config file published: config/google2fa.php');
    }

    private function copyLangFiles(): void
    {
        $this->composerInstaller->printStep(5, 6, 'Copying lang files');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }

        $destination = lang_path('en/google2fa.php');

        if (file_exists($destination)) {
            $this->composerInstaller->printConfigPublished('Skipped lang/en/google2fa.php: the file already exists.');

            return;
        }

        $this->stubCopier->copy(__DIR__.'/../../Stubs/Google2FA/lang/en/google2fa.stub', $destination);
        $this->composerInstaller->printConfigPublished('Lang file published: lang/en/google2fa.php');
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(6, 6, 'Registering routes');

        if (! is_dir(base_path('routes'))) {
            mkdir(base_path('routes'), 0755, true);
        }

        $this->writeStubIfMissing(
            __DIR__.'/../../Stubs/Google2FA/routes/two-factor-auth.stub',
            base_path('routes/'.self::ROUTES_FILE_NAME),
            'routes/'.self::ROUTES_FILE_NAME
        );

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
                "Two-factor authentication routes already required in {$this->apiRoutesPath}"
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
