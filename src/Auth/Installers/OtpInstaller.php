<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;

final class OtpInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Mails',
        'Authentication/Domain/Actions',
        'Authentication/Domain/Contracts',
        'Authentication/Domain/OtpSenders',
        'Authentication/Domain/Models',
        'Authentication/Domain/Exceptions',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
    ) {
    }

    public function install(): void
    {
        $this->createAuthFiles();
        $this->copyMigration();
        $this->printNextSteps();

        $this->composerInstaller->printSuccess('OTP installed successfully!');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(1, 2, 'Creating OTP files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/Otp/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Controllers/OtpSendController.stub'       => 'App/Controllers/OtpSendController.php',
            '/Controllers/OtpVerifyController.stub'     => 'App/Controllers/OtpVerifyController.php',
            '/Requests/OtpSendRequest.stub'             => 'App/Requests/OtpSendRequest.php',
            '/Requests/OtpVerifyRequest.stub'           => 'App/Requests/OtpVerifyRequest.php',
            '/Mails/OtpMail.stub'                       => 'App/Mails/OtpMail.php',
            '/Actions/SendOtpAction.stub'               => 'Domain/Actions/SendOtpAction.php',
            '/Actions/VerifyOtpAction.stub'             => 'Domain/Actions/VerifyOtpAction.php',
            '/Contracts/OtpSenderInterface.stub'        => 'Domain/Contracts/OtpSenderInterface.php',
            '/OtpSenders/EmailOtpSender.stub'           => 'Domain/OtpSenders/EmailOtpSender.php',
            '/Models/Otp.stub'                          => 'Domain/Models/Otp.php',
            '/Exceptions/InvalidOtpException.stub'      => 'Domain/Exceptions/InvalidOtpException.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(2, 2, 'Copying migration files');

        $stub = __DIR__ . '/../../Stubs/Otp/database/migrations/create_otps_table.stub';
        $timestamp = date('Y_m_d_His');
        $destination = "database/migrations/{$timestamp}_create_otps_table.php";

        copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }

    private function printNextSteps(): void
    {
        $this->command->line('');
        $this->command->line("\e[0;35m📋 Next steps:\e[0m");
        $this->command->line('');
        $this->command->line("\e[0;35m  1. Register the OTP sender binding in AppServiceProvider::register():\e[0m");
        $this->command->line("\e[0;35m     \$this->app->bind(\e[0m");
        $this->command->line("\e[0;35m         \\Lightit\\Authentication\\Domain\\Contracts\\OtpSenderInterface::class,\e[0m");
        $this->command->line("\e[0;35m         \\Lightit\\Authentication\\Domain\\OtpSenders\\EmailOtpSender::class\e[0m");
        $this->command->line("\e[0;35m     );\e[0m");
        $this->command->line('');
        $this->command->line("\e[0;35m  2. Add the routes to routes/api.php:\e[0m");
        $this->command->line("\e[0;35m     Route::post('otp/send', OtpSendController::class);\e[0m");
        $this->command->line("\e[0;35m     Route::post('otp/verify', OtpVerifyController::class);\e[0m");
        $this->command->line('');
        $this->command->line("\e[0;35m  3. Run: php artisan migrate\e[0m");
        $this->command->line('');
    }
}
