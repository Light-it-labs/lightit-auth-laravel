<?php

declare(strict_types=1);

namespace Lightit\Auth\Installers;

use Illuminate\Console\Command;
use Lightit\Contracts\AuthInstallerInterface;
use Lightit\Tools\FileManipulator;

final class JWTInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Transformers',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly FileManipulator $fileManipulator
    ) {}

    public function install(): void
    {
        $this->command->info('Installing JWT authentication...');

        if (! $this->composerInstaller->requirePackages(['php-open-source-saver/jwt-auth:^2.0'])) {
            $this->command->error('Failed to install php-open-source-saver/jwt-auth');

            return;
        }

        $this->addServiceProvider();
        $this->publishConfiguration();
        $this->generateSecret();
        $this->generateCerts();
        $this->createAuthFiles();

        $this->command->info('JWT authentication installed successfully!');
    }

    private function addServiceProvider(): void
    {
        $this->command->info('Step 1/5: Adding service provider...');

        $this->fileManipulator->replaceInFile(
            "'providers' => [",
            "'providers' => [".PHP_EOL."        \PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider::class,",
            config_path('app.php')
        );
    }

    private function publishConfiguration(): void
    {
        $this->command->info('Step 2/5: Publishing configuration...');

        $this->command->call('vendor:publish', [
            '--provider' => '\PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider',
        ]);

        $this->copyConfigFiles();
    }

    private function copyConfigFiles(): void
    {
        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__.'/../../Stubs/jwt/config/jwt.stub',
            config_path('jwt.php')
        );
    }

    private function generateSecret(): void
    {
        $this->command->info('Step 3/5: Generating JWT secret...');
        $this->command->call('jwt:secret');
    }

    private function generateCerts(): void
    {
        $this->command->info('Step 4/5: Generating Certificate...');
        $this->command->call('jwt:generate-certs', [
            '--force' => true,
            '--algo' => 'rsa',
            '--bits' => 4096,
            '--sha' => 512,
        ]);
    }

    private function createAuthFiles(): void
    {
        $this->command->info('Step 5/5: Creating authentication files...');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__.'/../../Stubs/jwt/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Requests/LoginRequest.stub' => 'App/Requests/LoginRequest.php',
            '/Controllers/LoginController.stub' => 'App/Controllers/LoginController.php',
            '/Controllers/LogoutController.stub' => 'App/Controllers/LogoutController.php',
            '/Controllers/RefreshController.stub' => 'App/Controllers/RefreshController.php',
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
            '/Actions/LogoutAction.stub' => 'Domain/Actions/LogoutAction.php',
            '/DataTransferObjects/LoginDto.stub' => 'Domain/DataTransferObjects/LoginDto.php',
            '/Transformers/LoginTransformer.stub' => 'App/Transformers/LoginTransformer.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath.$stub,
                base_path("src/Authentication/{$destination}")
            );
        }
    }
}
