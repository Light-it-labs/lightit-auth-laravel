<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;

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

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

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
        $this->copyMiddlewares();
        $this->copyConfigFiles();
        $this->copyLangFiles();

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

        $stubsPath = __DIR__ . '/../../Stubs/Google2FA/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
            '/Actions/Pipes/LoginContext.stub' => 'Domain/Actions/Pipes/LoginContext.php',
            '/Actions/Pipes/ValidateCredentials.stub' => 'Domain/Actions/Pipes/ValidateCredentials.php',
            '/Actions/Pipes/ResolveUser.stub' => 'Domain/Actions/Pipes/ResolveUser.php',
            '/Actions/Pipes/IssueAccessTokenIfNoFinalToken.stub' => 'Domain/Actions/Pipes/IssueAccessTokenIfNoFinalToken.php',
            '/Actions/Pipes/BuildLoginResult.stub' => 'Domain/Actions/Pipes/BuildLoginResult.php',
            '/Actions/Pipes/IssueTwoFactorSetupTokenIfMandatory.stub' => 'Domain/Actions/Pipes/IssueTwoFactorSetupTokenIfMandatory.php',
            '/Actions/Pipes/IssueTwoFactorChallengeTokenIfEnabled.stub' => 'Domain/Actions/Pipes/IssueTwoFactorChallengeTokenIfEnabled.php',
            '/Actions/DisableTwoFactorAuthenticationAction.stub' => 'Domain/Actions/DisableTwoFactorAuthenticationAction.php',
            '/Actions/SetupTwoFactorAuthenticationAction.stub' => 'Domain/Actions/SetupTwoFactorAuthenticationAction.php',
            '/Actions/GenerateQRCodeAction.stub' => 'Domain/Actions/GenerateQRCodeAction.php',
            '/Actions/CompleteTwoFactorAuthenticationAction.stub' => 'Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            '/Actions/GenerateRecoveryCodesAction.stub' => 'Domain/Actions/GenerateRecoveryCodesAction.php',
            '/Actions/VerifyOtpAction.stub' => 'Domain/Actions/VerifyOtpAction.php',
            '/Actions/VerifyRecoveryCodeAction.stub' => 'Domain/Actions/VerifyRecoveryCodeAction.php',
            '/Actions/VerifyTwoFactorToken.stub' => 'Domain/Actions/VerifyTwoFactorToken.php',
            '/Actions/PasswordValidatorAction.stub' => 'Domain/Actions/PasswordValidatorAction.php',
            '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
            '/DataTransferObjects/TwoFactorSetupDto.stub' => 'Domain/DataTransferObjects/TwoFactorSetupDto.php',
            '/DataTransferObjects/TwoFactorTokenPayloadDto.stub' => 'Domain/DataTransferObjects/TwoFactorTokenPayloadDto.php',
            '/Enums/TwoFactorReason.stub' => 'Domain/Enums/TwoFactorReason.php',
            '/Exceptions/TwoFactorAuthException.stub' => 'Domain/Exceptions/TwoFactorAuthException.php',
            '/Resources/TwoFactorAuthenticationSetUpResource.stub' => 'App/Resources/TwoFactorAuthenticationSetUpResource.php',
            '/Controllers/DisableTwoFactorAuthenticationController.stub' => 'App/Controllers/DisableTwoFactorAuthenticationController.php',
            '/Controllers/SetupTwoFactorAuthenticationController.stub' => 'App/Controllers/SetupTwoFactorAuthenticationController.php',
            '/Controllers/CompleteTwoFactorAuthenticationController.stub' => 'App/Controllers/CompleteTwoFactorAuthenticationController.php',
            '/Controllers/RegenerateRecoveryCodesController.stub' => 'App/Controllers/RegenerateRecoveryCodesController.php',
            '/Controllers/VerifyRecoveryCodeController.stub' => 'App/Controllers/VerifyRecoveryCodeController.php',
            '/Requests/SetupTwoFactorAuthenticationRequest.stub' => 'App/Requests/SetupTwoFactorAuthenticationRequest.php',
            '/Requests/CompleteTwoFactorAuthenticationRequest.stub' => 'App/Requests/CompleteTwoFactorAuthenticationRequest.php',
            '/Requests/DisableTwoFactorAuthenticationRequest.stub' => 'App/Requests/DisableTwoFactorAuthenticationRequest.php',
            '/Requests/GenerateRecoveryCodesRequest.stub' => 'App/Requests/GenerateRecoveryCodesRequest.php',
            '/Requests/VerifyRecoveryCodeRequest.stub' => 'App/Requests/VerifyRecoveryCodeRequest.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
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

        $stub = __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub';
        $destination = 'database/migrations/2024_03_18_220301_add_two_factor_authentication_columns.php';

        copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function copyMiddlewares(): void
    {
        $this->composerInstaller->printStep(4, 6, 'Copying Middlewares classes');

        $destinationFolder = 'src/Shared/App/Middlewares/';

        if (! is_dir($path = base_path($destinationFolder))) {
            mkdir($path, 0755, true);
        }

        $stubNames = [
            'ActiveTwoFactorAuthenticationMiddleware',
            'InactiveTwoFactorAuthenticationMiddleware',
        ];

        foreach ($stubNames as $stubName) {
            $stub = __DIR__ . "/../../Stubs/Google2FA/Auth/Middlewares/$stubName.stub";
            $destination = "{$destinationFolder}{$stubName}.php";

            copy(
                $stub,
                base_path($destination)
            );
            $this->composerInstaller->printMiddlewareCreated("Created: {$destination}");
        }
    }

    private function copyConfigFiles(): void
    {
        $this->composerInstaller->printStep(5, 6, 'Copying config files');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__ . '/../../Stubs/Google2FA/config/google2fa.stub',
            config_path('google2fa.php')
        );
        $this->composerInstaller->printConfigPublished('Config file published: config/google2fa.php');
    }

    private function copyLangFiles(): void
    {
        $this->composerInstaller->printStep(6, 6, 'Copying lang files');

        if (! is_dir(lang_path('en'))) {
            mkdir(lang_path('en'), 0755, true);
        }
        copy(
            __DIR__ . '/../../Stubs/Google2FA/lang/en/google2fa.stub',
            lang_path('en/google2fa.php')
        );
        $this->composerInstaller->printConfigPublished('Lang file published: lang/en/google2fa.php');
    }

}
