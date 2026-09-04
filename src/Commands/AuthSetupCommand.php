<?php

declare(strict_types=1);

namespace Lightitlabs\Commands;

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\ForgotPasswordInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Auth\Installers\GoogleSSOInstaller;
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Auth\Installers\PasskeysFrontendInstaller;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

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

        $featureOptions = array_column(
            array_map(
                fn (Feature $f) => ['value' => $f->value, 'label' => $f->label()],
                Feature::cases()
            ),
            'label',
            'value'
        );

        $selectedValues = multiselect(
            label: 'Select features',
            options: $featureOptions,
            hint: 'Press [space] to select, [enter] to confirm.'
        );

        $selected = [];

        foreach ($selectedValues as $value) {
            $selected[] = Feature::from((string) $value);
        }

        $this->setupFeatures($selected);

        $this->printSuccess('Authentication setup completed!');

        return self::SUCCESS;
    }

    /**
     * @param array<Feature> $features
     */
    protected function setupFeatures(array $features): void
    {
        foreach ($features as $feature) {
            match ($feature) {
                Feature::TwoFactorAuthentication => $this->setup2FA(),
                Feature::RolesAndPermissions => $this->setupRolesAndPermissions(),
                Feature::Otp => $this->setupOtp(),
                Feature::ForgotPassword => $this->setupForgotPassword(),
                Feature::GoogleSso => $this->setupGoogleSSO(),
                Feature::Passkeys => $this->setupPasskeys(),
            };
        }
    }

    protected function setupGoogleSSO(): void
    {
        $this->printBoxedMessage('Setting up Google SSO...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $googleSSOInstaller = new GoogleSSOInstaller($this, $composerInstaller, $stubCopier);
        $googleSSOInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setup2FA(): void
    {
        $this->printBoxedMessage('Setting up 2FA...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $google2FAInstaller = new Google2FAInstaller($this, $composerInstaller, $stubCopier);
        $google2FAInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->printBoxedMessage('Setting up Roles and Permissions...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $laravelPermission = new LaravelPermissionInstaller($this, $composerInstaller, $stubCopier);
        $laravelPermission->install();
        $this->printSectionSeparator();
    }

    protected function setupOtp(): void
    {
        $this->printBoxedMessage('Setting up OTP...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $otpInstaller = new OtpInstaller($composerInstaller, $stubCopier);
        $otpInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupForgotPassword(): void
    {
        $this->printBoxedMessage('Setting up Forgot Password...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $forgotPasswordInstaller = new ForgotPasswordInstaller($composerInstaller, $stubCopier);
        $forgotPasswordInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupPasskeys(): void
    {
        $this->printBoxedMessage('🛠 Setting up Passkeys...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $passkeysInstaller = new PasskeysInstaller($this, $composerInstaller, $stubCopier);
        $passkeysInstaller->install();
        $this->printSectionSeparator();

        $this->setupPasskeysFrontend();
    }

    protected function setupPasskeysFrontend(): void
    {
        $this->printBoxedMessage('🛠 Setting up Passkeys frontend...');

        $manifest = new FrontendPackageManifest;

        $frontendInstaller = new PasskeysFrontendInstaller(
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
}
