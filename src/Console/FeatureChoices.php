<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Lightitlabs\Enums\Feature;

/**
 * Plain-text option labels, so the prompt's non-interactive fallback stays readable; the
 * {@see FeatureSelectRenderer} splits them back into columns.
 */
final class FeatureChoices
{
    private const SEPARATOR = ' — ';

    private const INSTALLED_TAG = ' (installed)';

    private const DOCS_PATH = 'vendor/light-it-labs/lightit-auth-laravel/docs/';

    public function __construct(private readonly string $applicationRoot)
    {
    }

    /**
     * @param array<int, Feature> $features
     *
     * @return array<string, string>
     */
    public function options(array $features): array
    {
        $options = [];

        foreach ($features as $feature) {
            $options[$feature->value] = $feature->label()
                . self::SEPARATOR . self::description($feature)
                . (is_file($this->applicationRoot . '/' . self::installedMarker($feature)) ? self::INSTALLED_TAG : '');
        }

        return $options;
    }

    public static function docs(Feature $feature): string
    {
        return self::DOCS_PATH . match ($feature) {
            Feature::TwoFactorAuthentication => 'google-2fa.md',
            Feature::RolesAndPermissions => 'permission.md',
            Feature::Otp => 'otp.md',
            Feature::ForgotPassword => 'forgot-password.md',
            Feature::GoogleSso => 'google-sso.md',
        };
    }

    /**
     * @return array{name: string, description: string, installed: bool}
     */
    public static function parse(string $label): array
    {
        $installed = str_ends_with($label, self::INSTALLED_TAG);
        $text = $installed ? substr($label, 0, -\strlen(self::INSTALLED_TAG)) : $label;
        [$name, $description] = array_pad(explode(self::SEPARATOR, $text, 2), 2, '');

        return ['name' => $name, 'description' => $description, 'installed' => $installed];
    }

    private static function description(Feature $feature): string
    {
        return match ($feature) {
            Feature::TwoFactorAuthentication => 'Authenticator app at login, recovery codes, screens',
            Feature::RolesAndPermissions => 'spatie/laravel-permission, role catalog and seeders',
            Feature::Otp => 'One-time password sent by email',
            Feature::ForgotPassword => 'Reset-link email and password reset endpoints',
            Feature::GoogleSso => 'Sign in with a Google account',
        };
    }

    /**
     * A file the feature's installer writes into the application, relative to its root.
     */
    private static function installedMarker(Feature $feature): string
    {
        return match ($feature) {
            Feature::TwoFactorAuthentication => 'config/google2fa.php',
            Feature::RolesAndPermissions => 'config/permission.php',
            Feature::Otp => 'config/otp.php',
            Feature::ForgotPassword => 'src/Authentication/App/Controllers/ForgotPasswordController.php',
            Feature::GoogleSso => 'src/Authentication/Domain/Actions/GoogleLoginAction.php',
        };
    }
}
