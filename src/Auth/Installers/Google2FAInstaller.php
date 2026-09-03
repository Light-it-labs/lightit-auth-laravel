<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;
use RuntimeException;

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

        $this->copyAuthFiles(__DIR__.'/../../Stubs/Google2FA/Auth');
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
            '/Actions/CompleteTwoFactorAuthenticationAction.stub' => 'Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            '/Actions/VerifyRecoveryCodeAction.stub' => 'Domain/Actions/VerifyRecoveryCodeAction.php',
            '/Actions/Pipes/IssueAccessTokenIfNoFinalToken.stub' => 'Domain/Actions/Pipes/IssueAccessTokenIfNoFinalToken.php',
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
            $this->writeStubOrFail(
                $stubsPath.$stub,
                base_path("src/Authentication/{$destination}"),
                "src/Authentication/{$destination}"
            );
        }
    }

    private function writeStubOrFail(string $source, string $destination, string $label): void
    {
        if (! copy($source, $destination)) {
            throw new RuntimeException("Failed to copy stub to {$label}");
        }

        $this->composerInstaller->printFileCreated("Created: {$label}");
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

        copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function copyConfigFiles(): void
    {
        $this->composerInstaller->printStep(4, 6, 'Copying config files');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__.'/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );
        $this->composerInstaller->printConfigPublished('Config file published: config/google2fa.php');
    }

    private function copyLangFiles(): void
    {
        $this->composerInstaller->printStep(5, 6, 'Copying lang files');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        copy(
            __DIR__.'/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );
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
                'Two-factor authentication routes already required in routes/api.php'
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

        $this->overwriteStub($source, $destination);
        $this->composerInstaller->printFileCreated("Created: {$label}");
    }

    private function overwriteStub(string $source, string $destination): void
    {
        if (! file_exists($source)) {
            throw new RuntimeException("Missing stub: {$source}");
        }

        if (! copy($source, $destination)) {
            throw new RuntimeException("Could not write {$destination}");
        }
    }
}
