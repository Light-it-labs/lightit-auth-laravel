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
use Lightitlabs\Console\FeatureChoices;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Console\SetupTheme;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\PackageVersion;
use Lightitlabs\Tools\StubCopier;
use Lightitlabs\Tools\StubRenderer;

use function Laravel\Prompts\multiselect;

class AuthSetupCommand extends Command
{
    private SetupOutput|null $setupOutput = null;

    protected $signature = 'auth:setup {--frontend-path= : Path to the React project (defaults to a sibling directory named frontend, front or <app>-frontend), used when Two-Factor Authentication is selected; an invalid path fails the whole command even if Two-Factor Authentication is not selected}
        {--feature=* : Feature to install without asking (two-factor-authentication, roles-and-permissions, forgot-password); repeat it for more than one}';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
    {
        return SetupTheme::during($this->output->isDecorated(), function (): int {
            if (! $this->frontendPathIsValid()) {
                return self::FAILURE;
            }

            $this->setupOutput()->header(
                (new PackageVersion())->resolve(),
                base_path(),
                (new FrontendProjectLocator(new FrontendPackageManifest()))->locate(
                    base_path(),
                    $this->frontendPathOption()
                ),
            );

            $selected = $this->selectedFeatures();

            if ($selected === null) {
                return self::FAILURE;
            }

            $failedFeatures = $this->setupFeatures($selected);

            $this->setupOutput()->summary();

            return $failedFeatures === [] ? self::SUCCESS : self::FAILURE;
        });
    }

    protected function setupOutput(): SetupOutput
    {
        return $this->setupOutput ??= SetupOutput::for($this->output);
    }

    /**
     * @return array<Feature>|null null when the --feature values are not valid
     */
    private function selectedFeatures(): array|null
    {
        $requested = array_map(strval(...), (array) $this->option('feature'));
        $selectable = array_map(static fn (Feature $feature): string => $feature->value, Feature::selectable());

        if ($requested === [] && $this->input->isInteractive()) {
            $requested = array_map(strval(...), multiselect(
                label: 'Select features',
                options: (new FeatureChoices(base_path()))->options(Feature::selectable()),
                hint: 'Press [space] to select, [enter] to confirm.'
            ));
        }

        $unknown = array_diff($requested, $selectable);

        if ($unknown !== [] || ($requested === [] && ! $this->input->isInteractive())) {
            $this->error(
                ($unknown === [] ? 'No feature selected.' : 'Unknown --feature: ' . implode(', ', $unknown) . '.')
                . ' Pass --feature with one of: ' . implode(', ', $selectable) . '.'
            );

            return null;
        }

        return array_map(static fn (string $value): Feature => Feature::from($value), array_values($requested));
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
        $failedFeatures = [];

        foreach ($features as $feature) {
            $succeeded = $this->setupOutput()->feature(
                $feature->label(),
                fn () => $this->setupFeature($feature),
                FeatureChoices::docs($feature),
            );

            if (! $succeeded) {
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
        };
    }

    protected function setupGoogleSSO(): void
    {
        $composerInstaller = new ComposerInstaller($this->setupOutput());
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $googleSSOInstaller = new GoogleSSOInstaller($this->setupOutput(), $composerInstaller, $stubCopier);
        $googleSSOInstaller->install();
    }

    protected function setup2FA(): void
    {
        $composerInstaller = new ComposerInstaller($this->setupOutput());
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $google2FAInstaller = new Google2FAInstaller($this->setupOutput(), $composerInstaller, $stubCopier);
        $google2FAInstaller->install();

        $this->setup2FAFrontend();
    }

    protected function setup2FAFrontend(): void
    {
        $manifest = new FrontendPackageManifest();

        $frontendInstaller = new Google2FAFrontendInstaller(
            $this->setupOutput(),
            new StubRenderer(),
            new FrontendProjectLocator($manifest),
            $manifest,
            base_path(),
            $this->frontendPathOption(),
        );

        $frontendInstaller->install();
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

    protected function setupRolesAndPermissions(): void
    {
        $composerInstaller = new ComposerInstaller($this->setupOutput());
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $laravelPermission = new LaravelPermissionInstaller(
            $this,
            $this->setupOutput(),
            $composerInstaller,
            $stubCopier
        );
        $laravelPermission->install();
    }

    protected function setupOtp(): void
    {
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $otpInstaller = new OtpInstaller($this->setupOutput(), $stubCopier);
        $otpInstaller->install();
    }

    protected function setupForgotPassword(): void
    {
        $stubCopier = new StubCopier(OriginMarker::resolved());
        $forgotPasswordInstaller = new ForgotPasswordInstaller($this->setupOutput(), $stubCopier);
        $forgotPasswordInstaller->install();
    }
}
