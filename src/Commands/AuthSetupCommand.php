<?php

declare(strict_types=1);

namespace Lightitlabs\Commands;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendUsageScanner;
use Lightitlabs\Auth\Frontend\TypeScriptPatcher;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\ForgotPasswordInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Auth\Installers\GoogleSSOInstaller;
use Lightitlabs\Auth\Installers\JwtInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Auth\Installers\SanctumCookieFrontendInstaller;
use Lightitlabs\Auth\Installers\SanctumCookieInstaller;
use Lightitlabs\Auth\Installers\SanctumInstaller;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Enums\AuthDriver;
use Lightitlabs\Tools\FileManipulator;
use Lightitlabs\Tools\StubRenderer;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\multiselect;

class AuthSetupCommand extends Command
{
    use LightitConsoleOutput;

    public function __construct()
    {
        parent::__construct();
        $this->initializeOutput($this);
    }

    protected $name = 'auth:setup';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
    {
        $this->printBanner();

        $drivers = $this->resolveDrivers();

        if ($drivers === null) {
            return self::FAILURE;
        }

        $enable2FA = confirm(
            label: 'Would you like to enable Two-Factor Authentication?',
            default: false,
        );

        $enableRolesAndPermissions = confirm(
            label: 'Would you like to enable Roles and Permissions?',
            default: false,
        );

        // OTP requires a token-based driver; SPA cookie sessions handle re-auth differently
        $hasTokenDriver = in_array(AuthDriver::Jwt, $drivers, true)
            || in_array(AuthDriver::SanctumApiToken, $drivers, true);

        $enableOtp = $hasTokenDriver && confirm(
            label: 'Would you like to enable OTP (one-time password)?',
            default: false,
        );

        $enableForgotPassword = confirm(
            label: 'Would you like to enable the Forgot Password flow?',
            default: false,
        );

        try {
            $this->setupDrivers($drivers);

            if ($enable2FA) {
                $this->setup2FA($drivers);
            }

            if ($enableRolesAndPermissions) {
                $this->setupRolesAndPermissions();
            }

            if ($enableOtp) {
                $this->setupOtp();
            }

            if ($enableForgotPassword) {
                $this->setupForgotPassword();
            }

            if ($this->shouldSetupFrontend($drivers)) {
                $this->setupFrontend();
            }
        } catch (Throwable $exception) {
            $this->printFailure('Authentication setup failed: ' . $exception->getMessage());
            $this->printReproducibleCommand($drivers);

            return self::FAILURE;
        }

        $this->printSuccess('Authentication setup completed!');
        $this->printReproducibleCommand($drivers);

        return self::SUCCESS;
    }

    /**
     * @return list<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            new InputOption(
                name: 'driver',
                mode: InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,
                description: 'Authentication driver slug. Repeatable and comma-separated.',
                suggestedValues: AuthDriver::slugs(),
            ),
            new InputOption(
                name: 'frontend-path',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the React project root.',
            ),
            new InputOption(
                name: 'skip-frontend',
                mode: InputOption::VALUE_NONE,
                description: 'Do not generate the frontend authentication layer.',
            ),
        ];
    }

    /**
     * @return list<AuthDriver>|null
     */
    private function resolveDrivers(): array|null
    {
        $requested = $this->requestedDriverSlugs();

        if ($requested !== []) {
            return $this->driversFromSlugs($requested);
        }

        if (! $this->canPrompt()) {
            error('The --driver option is required when running without interaction.');
            $this->printValidDrivers();

            return null;
        }

        return $this->promptForDrivers();
    }

    /**
     * Mirrors the condition Laravel uses to decide whether prompts render
     * interactively, so this guard can never disagree with the prompt itself.
     */
    private function canPrompt(): bool
    {
        return $this->input->isInteractive()
            && defined('STDIN')
            && stream_isatty(STDIN);
    }

    /**
     * @return list<string>
     */
    private function requestedDriverSlugs(): array
    {
        /** @var list<string> $requested */
        $requested = $this->option('driver');

        $slugs = [];

        foreach ($requested as $value) {
            foreach (explode(',', $value) as $candidate) {
                $slug = mb_strtolower(trim($candidate));

                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<AuthDriver>|null
     */
    private function driversFromSlugs(array $slugs): array|null
    {
        $drivers = [];

        foreach ($slugs as $slug) {
            $driver = AuthDriver::tryFrom($slug);

            if ($driver === null) {
                error(sprintf('Unknown authentication driver "%s".', $slug));
                $this->printValidDrivers();

                return null;
            }

            $drivers[] = $driver;
        }

        $conflict = self::driverConflict($drivers);

        if ($conflict !== null) {
            error($conflict);

            return null;
        }

        return $drivers;
    }

    /**
     * @return list<AuthDriver>
     */
    private function promptForDrivers(): array
    {
        do {
            $selected = array_filter(
                multiselect(
                    label: 'Select authentication drivers',
                    options: AuthDriver::options(),
                    required: true,
                    hint: 'Press [space] to select, [enter] to confirm.'
                ),
                'is_string'
            );

            $drivers = array_values(array_map(
                static fn (string $slug): AuthDriver => AuthDriver::from($slug),
                $selected,
            ));

            $conflict = self::driverConflict($drivers);

            if ($conflict !== null) {
                error($conflict);
                $drivers = [];
            }
        } while ($drivers === []);

        return $drivers;
    }

    /**
     * @param list<AuthDriver> $drivers
     */
    private static function driverConflict(array $drivers): string|null
    {
        $sanctumDrivers = array_filter($drivers, static fn (AuthDriver $driver): bool => in_array($driver, [
            AuthDriver::SanctumApiToken,
            AuthDriver::SanctumCookie,
        ], true));

        if (in_array(AuthDriver::Jwt, $drivers, true) && $sanctumDrivers !== []) {
            return 'You cannot select both JWT and Sanctum authentication drivers.';
        }

        if (count($sanctumDrivers) > 1) {
            return 'You cannot select both Sanctum API Token and Sanctum Cookie drivers simultaneously.';
        }

        return null;
    }

    private function printValidDrivers(): void
    {
        $this->line('Valid drivers: ' . implode(', ', AuthDriver::slugs()));
    }

    /**
     * @param list<AuthDriver> $drivers
     */
    private function printReproducibleCommand(array $drivers): void
    {
        $slugs = implode(',', array_map(static fn (AuthDriver $driver): string => $driver->value, $drivers));

        $command = "php artisan auth:setup --driver={$slugs}";

        $frontendPath = $this->option('frontend-path');

        if (is_string($frontendPath) && $frontendPath !== '') {
            $command .= " --frontend-path={$frontendPath}";
        }

        if ($this->option('skip-frontend') === true) {
            $command .= ' --skip-frontend';
        }

        $this->line('');
        $this->line("\e[0;35mRun this again unattended:\e[0m");
        $this->line("{$command} -n");
    }

    private function printBanner(): void
    {
        $this->output->writeln('');
        $this->output->writeln("\e[0;31m     _         _   _       ____            _                     \e[0m");
        $this->output->writeln("\e[0;31m    / \  _   _| |_| |__   |  _ \ __ _  ___| | ____ _  __ _  ___  \e[0m");
        $this->output->writeln("\e[0;31m   / _ \| | | | __| '_ \  | |_) / _` |/ __| |/ / _` |/ _` |/ _ \ \e[0m");
        $this->output->writeln("\e[0;31m  / ___ \ |_| | |_| | | | |  __/ (_| | (__|   < (_| | (_| |  __/ \e[0m");
        $this->output->writeln("\e[0;31m /_/   \_\__,_|\__|_| |_| |_|   \__,_|\___|_|\_\__,_|\__, |\___|  \e[0m");
        $this->output->writeln("\e[0;31m                                                     |___/       \e[0m");
        $this->output->writeln('');
        $this->output->writeln("\e[0;35mLight-it's package to streamline authentication, authorization,\e[0m");
        $this->output->writeln("\e[0;35mroles, and permissions setup in Laravel boilerplates.\e[0m");
        $this->output->writeln('');
    }

    /**
     * @param list<AuthDriver> $drivers
     */
    protected function setupDrivers(array $drivers): void
    {
        $setup = [
            AuthDriver::Jwt->value => fn () => $this->setupJWT(),
            AuthDriver::SanctumApiToken->value => fn () => $this->setupSanctum(),
            AuthDriver::SanctumCookie->value => fn () => $this->setupSanctumCookie(),
            AuthDriver::GoogleSso->value => fn () => $this->setupGoogleSSO(),
        ];

        foreach ($drivers as $driver) {
            $setup[$driver->value]();
        }
    }

    protected function setupJWT(): void
    {
        $this->printBoxedMessage('🛠 Setting up JWT...');

        $composerInstaller = new ComposerInstaller($this);
        $fileManipulator = new FileManipulator($this);
        $jwtInstaller = new JwtInstaller($this, $composerInstaller, $fileManipulator);
        $jwtInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupSanctum(): void
    {
        $this->printBoxedMessage('🛠 Setting up Sanctum API Token...');

        $composerInstaller = new ComposerInstaller($this);
        $sanctumInstaller = new SanctumInstaller($this, $composerInstaller);
        $sanctumInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupFrontend(): void
    {
        $this->printBoxedMessage('🛠 Setting up React frontend (cookie auth)...');

        $manifest = new FrontendPackageManifest();

        $frontendInstaller = new SanctumCookieFrontendInstaller(
            $this,
            new StubRenderer(),
            new FrontendProjectLocator($manifest),
            $manifest,
            new FrontendUsageScanner(),
            new TypeScriptPatcher(),
            base_path(),
            $this->frontendPathOption(),
        );

        $frontendInstaller->install();
        $this->printSectionSeparator();
    }

    /**
     * @param list<AuthDriver> $drivers
     */
    private function shouldSetupFrontend(array $drivers): bool
    {
        return in_array(AuthDriver::SanctumCookie, $drivers, true)
            && $this->option('skip-frontend') !== true;
    }

    private function frontendPathOption(): string|null
    {
        $path = $this->option('frontend-path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    protected function setupSanctumCookie(): void
    {
        $this->printBoxedMessage('🛠 Setting up Sanctum Cookie (SPA)...');

        $composerInstaller = new ComposerInstaller($this);
        $fileManipulator = new FileManipulator($this);
        $sanctumCookieInstaller = new SanctumCookieInstaller($this, $composerInstaller, $fileManipulator);
        $sanctumCookieInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupGoogleSSO(): void
    {
        $this->printBoxedMessage('🛠 Setting up Google SSO...');

        $composerInstaller = new ComposerInstaller($this);
        $jwtInstaller = new GoogleSSOInstaller($this, $composerInstaller);
        $jwtInstaller->install();
        $this->printSectionSeparator();
    }

    /**
     * @param list<AuthDriver> $drivers
     */
    protected function setup2FA(array $drivers): void
    {
        $this->printBoxedMessage('🛠 Setting up 2FA...');

        $driver = match (true) {
            in_array(AuthDriver::SanctumApiToken, $drivers, true) => AuthDriver::SanctumApiToken,
            in_array(AuthDriver::SanctumCookie, $drivers, true) => AuthDriver::SanctumCookie,
            default => AuthDriver::Jwt,
        };

        $composerInstaller = new ComposerInstaller($this);
        $google2FAInstaller = new Google2FAInstaller($this, $composerInstaller, $driver);
        $google2FAInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->printBoxedMessage('🛠 Setting up Roles and Permissions...');

        $composerInstaller = new ComposerInstaller($this);
        $laravelPermission = new LaravelPermissionInstaller($this, $composerInstaller);
        $laravelPermission->install();
        $this->printSectionSeparator();
    }

    protected function setupOtp(): void
    {
        $this->printBoxedMessage('🛠 Setting up OTP...');

        $composerInstaller = new ComposerInstaller($this);
        $otpInstaller = new OtpInstaller($composerInstaller);
        $otpInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupForgotPassword(): void
    {
        $this->printBoxedMessage('🛠 Setting up Forgot Password...');

        $composerInstaller = new ComposerInstaller($this);
        $forgotPasswordInstaller = new ForgotPasswordInstaller($composerInstaller);
        $forgotPasswordInstaller->install();
        $this->printSectionSeparator();
    }
}
