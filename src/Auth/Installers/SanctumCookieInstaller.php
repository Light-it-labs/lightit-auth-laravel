<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\FileManipulator;

final class SanctumCookieInstaller implements AuthInstallerInterface
{
    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Resources',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly FileManipulator $fileManipulator,
    ) {
    }

    public function install(): void
    {
        $this->command->call('install:api');

        $this->publishSanctumConfig();
        $this->addStatefulMiddleware();
        $this->createAuthFiles();

        $this->composerInstaller->printSuccess('Sanctum Cookie (SPA) Authentication installed successfully!');
    }

    private function publishSanctumConfig(): void
    {
        $this->composerInstaller->printStep(1, 3, 'Publishing Sanctum config with stateful_domains');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        copy(
            __DIR__ . '/../../Stubs/SanctumCookie/config/sanctum.stub',
            config_path('sanctum.php')
        );

        $this->composerInstaller->printConfigPublished('Config published: config/sanctum.php (includes stateful_domains)');
    }

    private function addStatefulMiddleware(): void
    {
        $this->composerInstaller->printStep(2, 3, 'Adding EnsureFrontendRequestsAreStateful middleware');

        $bootstrapPath = base_path('bootstrap/app.php');

        $this->fileManipulator->replaceInFile(
            '->withMiddleware(function (Middleware $middleware) {',
            '->withMiddleware(function (Middleware $middleware) {' . PHP_EOL
                . '        $middleware->statefulApi();',
            $bootstrapPath
        );

        $this->composerInstaller->printFileCreated('Middleware added to bootstrap/app.php: statefulApi()');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(3, 3, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        $stubsPath = __DIR__ . '/../../Stubs/SanctumCookie/Auth';

        $this->copyAuthFiles($stubsPath);
    }

    private function copyAuthFiles(string $stubsPath): void
    {
        $files = [
            '/Resources/LoginResource.stub' => 'App/Resources/LoginResource.php',
            '/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
            '/DataTransferObjects/CredentialsDto.stub' => 'Domain/DataTransferObjects/CredentialsDto.php',
            '/Requests/LoginRequest.stub' => 'App/Requests/LoginRequest.php',
            '/Controllers/LoginController.stub' => 'App/Controllers/LoginController.php',
            '/Controllers/LogoutController.stub' => 'App/Controllers/LogoutController.php',
            '/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
        ];

        foreach ($files as $stub => $destination) {
            copy(
                $stubsPath . $stub,
                base_path("src/Authentication/{$destination}")
            );
            $this->composerInstaller->printFileCreated("Created: src/Authentication/{$destination}");
        }
    }
}
