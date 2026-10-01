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
use Lightitlabs\Auth\Installers\LaravelPermissionInstaller;
use Lightitlabs\Auth\Installers\OtpInstaller;
use Lightitlabs\Auth\Installers\PasskeysFrontendInstaller;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Console\LightitConsoleOutput;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Exceptions\SetupAbortedException;
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

    protected $signature = 'auth:setup {--frontend-path= : Path to the React project (defaults to a sibling directory named frontend, front or <app>-frontend), used when Two-Factor Authentication or Passkeys is selected; an invalid path fails the whole command even if neither is selected}';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
    {
        if (! $this->frontendPathIsValid()) {
            return self::FAILURE;
        }

        $this->output->writeln('');
        $this->output->writeln("\e[0;31m     _         _   _       ____            _                     \e[0m");
        $this->output->writeln("\e[0;31m    / \  _   _| |_| |__   |  _ \ __ _  ___| | ____ _  __ _  ___  \e[0m");
        $this->output->writeln("\e[0;31m   / _ \| | | | __| '_ \  | |_) / _` |/ __| |/ / _` |/ _` |/ _ \ \e[0m");
        $this->output->writeln("\e[0;31m  / ___ \ |_| | |_| | | | |  __/ (_| | (__|   < (_| | (_| |  __/ \e[0m");
        $this->output->writeln("\e[0;31m /_/   \_\__,_|\__|_| |_| |_|   \__,_|\___|_|\_\__,_|\__, |\___|  \e[0m");
        $this->output->writeln("\e[0;31m                                                     |___/       \e[0m");
        $this->output->writeln('');
        $this->output->writeln("\e[0;35mLight-it's package to add optional auth features - 2FA, roles and\e[0m");
        $this->output->writeln("\e[0;35mpermissions, OTP and social login - on top of Laravel boilerplates.\e[0m");
        $this->output->writeln('');

        $featureOptions = array_column(
            array_map(
                fn (Feature $f) => ['value' => $f->value, 'label' => $f->label()],
                Feature::selectable()
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

        $failedFeatures = $this->setupFeatures($selected);

        if ($failedFeatures !== []) {
            $this->reportIncompleteSetup($failedFeatures);

            return self::FAILURE;
        }

        $this->printSuccess('Authentication setup completed!');

        return self::SUCCESS;
    }

    /**
     * Runs every feature even when one fails: they are independent, and every installer
     * skips the files that already exist, so re-running the failed ones afterwards is safe.
     *
     * @param array<Feature> $features
     *
     * @return array<Feature>
     */
    protected function setupFeatures(array $features): array
    {
        // Declaration order, not selection order: the passkeys frontend reads whether the
        // 2FA frontend is already in place to pick its login hook.
        $ordered = array_filter(
            Feature::cases(),
            static fn (Feature $feature): bool => \in_array($feature, $features, true),
        );

        $failedFeatures = [];

        foreach ($ordered as $feature) {
            try {
                $this->setupFeature($feature);
            } catch (Throwable $exception) {
                $this->reportFailedFeature($feature, $exception);
                $failedFeatures[] = $feature;
            }
        }

        return $failedFeatures;
    }

    protected function setupFeature(Feature $feature): void
    {
        match ($feature) {
            Feature::TwoFactorAuthentication => $this->setup2FA(),
            Feature::RolesAndPermissions => $this->setupRolesAndPermissions(),
            Feature::Otp => $this->setupOtp(),
            Feature::ForgotPassword => $this->setupForgotPassword(),
            Feature::GoogleSso => $this->setupGoogleSSO(),
            Feature::Passkeys => $this->setupPasskeys(),
        };
    }

    private function reportFailedFeature(Feature $feature, Throwable $exception): void
    {
        $this->printFailure("{$feature->label()} setup failed: {$exception->getMessage()}");
        $this->warn('The files it wrote before failing were kept. Continuing with the remaining features.');

        if (! $exception instanceof SetupAbortedException && $this->output->isVerbose()) {
            $this->line((string) $exception);
        }

        $this->printSectionSeparator();
    }

    /**
     * @param array<Feature> $failedFeatures
     */
    private function reportIncompleteSetup(array $failedFeatures): void
    {
        $labels = array_map(static fn (Feature $feature): string => $feature->label(), $failedFeatures);

        $this->printFailure('Authentication setup did not complete: ' . implode(', ', $labels) . ' failed.');
        $this->line(
            'Fix the cause above and run php artisan auth:setup again with the same features. It is safe to re-run: '
            . 'files that already exist are reported as Skipped and never overwritten.'
        );
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

        $this->setup2FAFrontend();
    }

    protected function setup2FAFrontend(): void
    {
        $this->printBoxedMessage('🛠 Setting up 2FA frontend...');

        $manifest = new FrontendPackageManifest();

        $frontendInstaller = new Google2FAFrontendInstaller(
            $this,
            new StubRenderer(),
            new FrontendProjectLocator($manifest),
            $manifest,
            base_path(),
            $this->frontendPathOption(),
        );

        $frontendInstaller->install();

        $this->printSectionSeparator();
    }

    private function frontendPathOption(): string|null
    {
        $value = $this->option('frontend-path');

        return \is_string($value) ? $value : null;
    }

    private function frontendPathIsValid(): bool
    {
        $path = $this->frontendPathOption();

        if ($path === null || $path === '') {
            return true;
        }

        $locator = new FrontendProjectLocator(new FrontendPackageManifest());

        if ($locator->locate(base_path(), $path) !== null) {
            return true;
        }

        $this->error('Invalid --frontend-path: ' . $locator->rejectionReason(base_path(), $path));

        return false;
    }

    protected function setupPasskeys(): void
    {
        $this->printBoxedMessage('Setting up Passkeys...');

        $composerInstaller = new ComposerInstaller($this);
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $passkeysInstaller = new PasskeysInstaller($this, $composerInstaller, $stubCopier);
        $passkeysInstaller->install();
        $this->printSectionSeparator();

        $this->printBoxedMessage('🛠 Setting up passkeys frontend...');

        $manifest = new FrontendPackageManifest();

        (new PasskeysFrontendInstaller(
            $this,
            new StubRenderer(),
            new FrontendProjectLocator($manifest),
            $manifest,
            base_path(),
            $this->frontendPathOption(),
        ))->install();

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
}
