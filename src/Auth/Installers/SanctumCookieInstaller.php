<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\EnvironmentKeys;
use Lightitlabs\Tools\FileManipulator;
use RuntimeException;

final class SanctumCookieInstaller implements AuthInstallerInterface
{
    private const TOTAL_STEPS = 7;

    private const ROUTES_MARKER = '// lightit-auth: authentication routes';

    private const CONTROLLER_NAMESPACE = 'Lightit\Authentication\App\Controllers';

    private const AUTH_DIRECTORIES = [
        'Authentication/App/Controllers',
        'Authentication/App/Requests',
        'Authentication/App/Resources',
        'Authentication/Domain/Actions',
        'Authentication/Domain/DataTransferObjects',
    ];

    private const SHARED_FILES = [
        'Shared/Auth/DataTransferObjects/CredentialsDto.stub' => 'Domain/DataTransferObjects/CredentialsDto.php',
        'Shared/Auth/Requests/LoginRequest.stub' => 'App/Requests/LoginRequest.php',
        'Shared/Auth/Resources/UserResource.stub' => 'App/Resources/UserResource.php',
    ];

    private const DRIVER_FILES = [
        'SanctumCookie/Auth/Resources/LoginResource.stub' => 'App/Resources/LoginResource.php',
        'SanctumCookie/Auth/Actions/LoginAction.stub' => 'Domain/Actions/LoginAction.php',
        'SanctumCookie/Auth/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
        'SanctumCookie/Auth/Controllers/LoginController.stub' => 'App/Controllers/LoginController.php',
        'SanctumCookie/Auth/Controllers/LogoutController.stub' => 'App/Controllers/LogoutController.php',
        'SanctumCookie/Auth/Controllers/CurrentUserController.stub' => 'App/Controllers/CurrentUserController.php',
        'SanctumCookie/Auth/DataTransferObjects/LoginDto.stub' => 'Domain/DataTransferObjects/LoginDto.php',
    ];

    private const ENVIRONMENT_FILES = ['.env.example', '.env'];

    private const ROUTE_CONTROLLERS = [
        'LoginController',
        'LogoutController',
        'CurrentUserController',
    ];

    private const GENERATED_ROUTE_PATTERNS = [
        'GET me' => '/(?:Route::|->)get\(\s*[\'"]\/?me[\'"]/',
        'POST auth/login' => '/(?:Route::|->)post\(\s*[\'"]\/?auth\/login[\'"]/',
        'POST auth/logout' => '/(?:Route::|->)post\(\s*[\'"]\/?auth\/logout[\'"]/',
    ];

    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly FileManipulator $fileManipulator,
        private readonly EnvironmentKeys $environmentKeys = new EnvironmentKeys(),
    ) {
    }

    public function install(): void
    {
        $sanctumConfigExisted = file_exists(config_path('sanctum.php'));

        if ($this->apiScaffoldingIsInstalled()) {
            $this->composerInstaller->printConfigPublished(
                'API scaffolding already installed: skipping install:api.'
            );
        } else {
            $this->command->call('install:api', $this->apiInstallArguments());
        }

        $this->publishSanctumConfig($sanctumConfigExisted);
        $this->publishCorsConfig();
        $this->addStatefulMiddleware();
        $this->createAuthFiles();
        $this->registerRoutes();
        $this->appendEnvironmentVariables();
        $this->printManualSteps();

        $this->composerInstaller->printSuccess('Sanctum Cookie (SPA) Authentication installed successfully!');
    }

    /**
     * Re-running `install:api` over a scaffolded app reports "API routes file already exists" as an
     * error and re-requires Sanctum for nothing, so a second run of this installer looks like it
     * failed when it did not.
     */
    private function apiScaffoldingIsInstalled(): bool
    {
        if (! file_exists(base_path('routes/api.php'))) {
            return false;
        }

        $bootstrap = base_path('bootstrap/app.php');

        if (! file_exists($bootstrap)) {
            return false;
        }

        return str_contains((string) file_get_contents($bootstrap), 'routes/api.php');
    }

    /**
     * `install:api` asks whether to run pending migrations and its prompt defaults to yes, so a
     * non-interactive run would migrate the consumer's database unasked. The option is version
     * guarded because this package supports illuminate/contracts ^11||^12||^13.
     *
     * @return array<string, bool>
     */
    private function apiInstallArguments(): array
    {
        $application = $this->command->getApplication();

        if ($application === null) {
            return [];
        }

        $definition = $application->find('install:api')->getDefinition();

        if (! $definition->hasOption('without-migration-prompt')) {
            return [];
        }

        return ['--without-migration-prompt' => true];
    }

    private function publishSanctumConfig(bool $configExisted): void
    {
        $this->composerInstaller->printStep(1, self::TOTAL_STEPS, 'Publishing Sanctum config with stateful_domains');

        if ($configExisted) {
            $this->command->warn(
                'Skipped config/sanctum.php: the file already existed before this run. ' .
                'Add your SPA origin to its stateful domains manually.'
            );

            return;
        }

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        if (! copy($this->stubPath('SanctumCookie/config/sanctum.stub'), config_path('sanctum.php'))) {
            throw new RuntimeException('Could not write config/sanctum.php');
        }

        $this->composerInstaller->printConfigPublished(
            'Config published: config/sanctum.php (includes stateful_domains)'
        );
    }

    private function publishCorsConfig(): void
    {
        $this->composerInstaller->printStep(2, self::TOTAL_STEPS, 'Publishing CORS config for credentialed requests');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $this->writeStub('SanctumCookie/config/cors.stub', config_path('cors.php'), 'config/cors.php');
    }

    private function addStatefulMiddleware(): void
    {
        $this->composerInstaller->printStep(
            3,
            self::TOTAL_STEPS,
            'Adding EnsureFrontendRequestsAreStateful middleware'
        );

        $bootstrapPath = base_path('bootstrap/app.php');

        if (! file_exists($bootstrapPath)) {
            $this->command->warn(
                'Could not find bootstrap/app.php. ' .
                'Please add $middleware->statefulApi() inside the withMiddleware() callback manually.'
            );

            return;
        }

        $before = (string) file_get_contents($bootstrapPath);

        if (str_contains($before, '$middleware->statefulApi()')) {
            $this->composerInstaller->printFileCreated(
                'Middleware already present in bootstrap/app.php: statefulApi()'
            );

            return;
        }

        $this->fileManipulator->replaceInFile(
            '->withMiddleware(function (Middleware $middleware): void {',
            '->withMiddleware(function (Middleware $middleware): void {' . PHP_EOL
                . '        $middleware->statefulApi();',
            $bootstrapPath
        );

        $after = (string) file_get_contents($bootstrapPath);

        if ($before === $after) {
            $this->command->warn(
                'Could not inject statefulApi() into bootstrap/app.php automatically. ' .
                'Please add $middleware->statefulApi() manually inside the withMiddleware() callback.'
            );

            return;
        }

        $this->composerInstaller->printFileCreated('Middleware added to bootstrap/app.php: statefulApi()');
    }

    private function createAuthFiles(): void
    {
        $this->composerInstaller->printStep(4, self::TOTAL_STEPS, 'Creating authentication files');

        foreach (self::AUTH_DIRECTORIES as $directory) {
            if (! is_dir($path = base_path("src/{$directory}"))) {
                mkdir($path, 0755, true);
            }
        }

        foreach ([...self::SHARED_FILES, ...self::DRIVER_FILES] as $stub => $destination) {
            $this->writeStub(
                $stub,
                base_path("src/Authentication/{$destination}"),
                "src/Authentication/{$destination}"
            );
        }
    }

    private function registerRoutes(): void
    {
        $this->composerInstaller->printStep(5, self::TOTAL_STEPS, 'Registering authentication routes');

        if (! is_dir($routesDirectory = base_path('routes'))) {
            mkdir($routesDirectory, 0755, true);
        }

        $this->writeStub(
            'SanctumCookie/routes/auth.stub',
            $routesDirectory . '/auth.php',
            'routes/auth.php'
        );

        $this->requireAuthRoutes($routesDirectory . '/api.php');
    }

    private function requireAuthRoutes(string $path): void
    {
        if (! file_exists($path)) {
            $this->command->warn(
                'Could not find routes/api.php. ' .
                "Please add require __DIR__.'/auth.php'; to your API route file manually."
            );

            return;
        }

        $before = (string) file_get_contents($path);

        if (str_contains($before, self::ROUTES_MARKER)) {
            $this->composerInstaller->printFileCreated('Authentication routes already required in routes/api.php');

            return;
        }

        file_put_contents(
            $path,
            rtrim($before) . PHP_EOL . PHP_EOL
                . self::ROUTES_MARKER . PHP_EOL
                . "require __DIR__.'/auth.php';" . PHP_EOL
        );

        if (! str_contains((string) file_get_contents($path), self::ROUTES_MARKER)) {
            $this->command->warn(
                "Could not append require __DIR__.'/auth.php'; to routes/api.php automatically. " .
                'Please add it manually.'
            );

            return;
        }

        $this->composerInstaller->printFileCreated("Updated routes/api.php: require __DIR__.'/auth.php';");
    }

    private function appendEnvironmentVariables(): void
    {
        $this->composerInstaller->printStep(6, self::TOTAL_STEPS, 'Appending session and CORS environment variables');

        $block = (string) file_get_contents($this->stubPath('SanctumCookie/env.stub'));

        foreach (self::ENVIRONMENT_FILES as $file) {
            $this->appendEnvironmentBlock(base_path($file), $file, $block);
        }
    }

    private function appendEnvironmentBlock(string $path, string $label, string $block): void
    {
        if (! file_exists($path)) {
            $this->command->warn(
                "Could not find {$label}. Please add the session and CORS variables to it manually."
            );

            return;
        }

        $current = (string) file_get_contents($path);

        $lines = [];
        $added = [];

        foreach (explode(PHP_EOL, $block) as $line) {
            $variable = $this->environmentKeys->parse($line);

            if ($variable === null) {
                $lines[] = $line;

                continue;
            }

            ['key' => $key, 'value' => $value] = $variable;

            if ($this->environmentKeys->isSet($current, $key)) {
                $this->command->warn(
                    "Skipped {$key} in {$label}: the key is already present. " .
                    "Cookie auth expects {$key}={$value}."
                );

                continue;
            }

            $lines[] = $line;
            $added[] = $key;
        }

        if ($added === []) {
            $this->composerInstaller->printFileCreated(
                "No changes to {$label}: every session and CORS key is already present."
            );

            return;
        }

        file_put_contents(
            $path,
            rtrim($current) . PHP_EOL . PHP_EOL . trim(implode(PHP_EOL, $lines)) . PHP_EOL
        );

        if ((string) file_get_contents($path) === $current) {
            $this->command->warn(
                "Could not append the session and CORS variables to {$label} automatically. " .
                'Please add them manually.'
            );

            return;
        }

        $this->composerInstaller->printFileCreated("Updated {$label}: " . implode(', ', $added));
    }

    private function printManualSteps(): void
    {
        $this->composerInstaller->printStep(7, self::TOTAL_STEPS, 'Manual steps');

        $this->command->line('routes/auth.php registers these single-action controllers:');

        foreach (self::ROUTE_CONTROLLERS as $controller) {
            $this->command->line('  - ' . self::CONTROLLER_NAMESPACE . '\\' . $controller);
        }

        $this->command->newLine();

        $this->warnAboutShadowedRoutes();

        $this->command->warn(
            'Your pre-existing protected routes were left untouched. ' .
            "Wrap them in Route::middleware('auth:sanctum')->group(...) in routes/api.php yourself - " .
            'this installer never restructures that file.'
        );

        $this->command->warn(
            'Point FRONTEND_URL and SANCTUM_STATEFUL_DOMAINS at your SPA origin before deploying.'
        );
    }

    private function warnAboutShadowedRoutes(): void
    {
        $path = base_path('routes/api.php');

        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);

        foreach (self::GENERATED_ROUTE_PATTERNS as $route => $pattern) {
            if (preg_match($pattern, $contents) !== 1) {
                continue;
            }

            $this->command->warn(
                "routes/api.php already declares {$route} above the generated require. " .
                'RouteCollection keys routes by method and URI, so the generated declaration ' .
                "replaces yours and routes/auth.php now answers {$route}. Delete the older " .
                'declaration - it is dead code, and leaving it there hides which handler runs.'
            );
        }
    }

    private function writeStub(string $stub, string $destination, string $label): void
    {
        if (file_exists($destination)) {
            $this->composerInstaller->printFileCreated("Skipped {$label}: the file already exists.");

            return;
        }

        $source = $this->stubPath($stub);

        if (! file_exists($source)) {
            throw new RuntimeException("Missing stub: {$stub}");
        }

        if (! copy($source, $destination)) {
            throw new RuntimeException("Could not write {$label}");
        }

        $this->composerInstaller->printFileCreated("Created: {$label}");
    }

    private function stubPath(string $stub): string
    {
        return __DIR__ . '/../../Stubs/' . $stub;
    }
}
