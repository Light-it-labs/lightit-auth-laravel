<?php

declare(strict_types=1);

namespace Lightitlabs\Commands;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\ForgotPasswordInstaller;
use Lightitlabs\Auth\Installers\Google2FAFrontendInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Auth\Installers\GoogleSSOInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionFrontendInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Auth\Installers\SanctumInstaller;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Enums\LoginMethod;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubRenderer;
use Throwable;

use function Laravel\Prompts\multiselect;

class AuthSetupCommand extends Command
{
    use LightitConsoleOutput;

    public function __construct()
    {
        parent::__construct();
        $this->initializeOutput($this);
    }

    protected $signature = 'auth:setup';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
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

        $loginMethods = $this->resolveLoginMethods();
        $features = $this->resolveFeatures();

        try {
            $this->setupLoginMethods($loginMethods);
            $this->setupFeatures($features);
        } catch (Throwable $exception) {
            $this->printFailure('Authentication setup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->printSuccess('Authentication setup completed!');

        return self::SUCCESS;
    }

    /**
     * Password is always enabled and cannot be deselected - it is added to the
     * result regardless of what the multiselect returns, so SSO-only is never
     * expressible.
     *
     * @return list<LoginMethod>
     */
    protected function resolveLoginMethods(): array
    {
        $selected = array_filter(
            multiselect(
                label: 'Select login methods',
                options: LoginMethod::options(),
                default: [LoginMethod::Password->value],
                hint: 'Password is always enabled. Press [space] to add Google SSO, [enter] to confirm.',
            ),
            'is_string'
        );

        $methods = array_values(array_map(
            static fn (string $slug): LoginMethod => LoginMethod::from($slug),
            $selected,
        ));

        if (! in_array(LoginMethod::Password, $methods, true)) {
            array_unshift($methods, LoginMethod::Password);
        }

        return $methods;
    }

    /**
     * @return list<Feature>
     */
    protected function resolveFeatures(): array
    {
        $selected = array_filter(
            multiselect(
                label: 'Select optional features',
                options: Feature::options(),
                hint: 'Press [space] to select, [enter] to confirm.',
            ),
            'is_string'
        );

        return array_values(array_map(
            static fn (string $slug): Feature => Feature::from($slug),
            $selected,
        ));
    }

    /**
     * @param  list<LoginMethod>  $methods
     */
    protected function setupLoginMethods(array $methods): void
    {
        $setup = [
            LoginMethod::Password->value => fn () => $this->setupSanctum(),
            LoginMethod::GoogleSso->value => fn () => $this->setupGoogleSSO(),
        ];

        foreach ($methods as $method) {
            $setup[$method->value]();
        }
    }

    protected function setupSanctum(): void
    {
        $this->printBoxedMessage('🛠 Setting up Sanctum...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $sanctumInstaller = new SanctumInstaller($this, $composerInstaller, $stubCopier);
        $sanctumInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupGoogleSSO(): void
    {
        $this->printBoxedMessage('🛠 Setting up Google SSO...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $googleSSOInstaller = new GoogleSSOInstaller($this, $composerInstaller, $stubCopier);
        $googleSSOInstaller->install();
        $this->printSectionSeparator();
    }

    /**
     * @param  list<Feature>  $features
     */
    protected function setupFeatures(array $features): void
    {
        foreach ($features as $feature) {
            match ($feature) {
                Feature::TwoFactorAuthentication => $this->setup2FA(),
                Feature::RolesAndPermissions => $this->setupRolesAndPermissions(),
                Feature::Otp => $this->setupOtp(),
                Feature::ForgotPassword => $this->setupForgotPassword(),
            };
        }
    }

    protected function setup2FA(): void
    {
        $this->printBoxedMessage('🛠 Setting up 2FA...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $google2FAInstaller = new Google2FAInstaller($this, $composerInstaller, $stubCopier);
        $google2FAInstaller->install();
        $this->printSectionSeparator();

        $this->setup2FAFrontend();
    }

    protected function setup2FAFrontend(): void
    {
        $this->printBoxedMessage('🛠 Setting up 2FA frontend...');

        $manifest = new FrontendPackageManifest;

        $frontendInstaller = new Google2FAFrontendInstaller(
            $this,
            new StubRenderer,
            OriginMarker::resolved(),
            new FrontendProjectLocator($manifest),
            $manifest,
            base_path(),
        );

        $frontendInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->printBoxedMessage('🛠 Setting up Roles and Permissions...');

        $composerInstaller = new ComposerInstaller($this);
        $laravelPermission = new LaravelPermissionInstaller(
            $this,
            $composerInstaller,
            new StubRenderer,
            OriginMarker::resolved(),
        );
        $laravelPermission->install();
        $this->printSectionSeparator();

        $this->setupRolesAndPermissionsFrontend();
    }

    protected function setupRolesAndPermissionsFrontend(): void
    {
        $this->printBoxedMessage('🛠 Setting up Roles and Permissions frontend...');

        $manifest = new FrontendPackageManifest;

        $frontendInstaller = new LaravelPermissionFrontendInstaller(
            $this,
            new StubRenderer,
            OriginMarker::resolved(),
            new FrontendProjectLocator($manifest),
            $manifest,
            base_path(),
        );

        $frontendInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupOtp(): void
    {
        $this->printBoxedMessage('🛠 Setting up OTP...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $otpInstaller = new OtpInstaller($composerInstaller, $stubCopier);
        $otpInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupForgotPassword(): void
    {
        $this->printBoxedMessage('🛠 Setting up Forgot Password...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $forgotPasswordInstaller = new ForgotPasswordInstaller($composerInstaller, $stubCopier);
        $forgotPasswordInstaller->install();
        $this->printSectionSeparator();
    }
}
