<?php

declare(strict_types=1);

namespace Lightitlabs\Enums;

enum Feature: string
{
    case TwoFactorAuthentication = 'two-factor-authentication';
    case RolesAndPermissions = 'roles-and-permissions';
    case Otp = 'otp';
    case ForgotPassword = 'forgot-password';
    case GoogleSso = 'google-sso';
    case Passkeys = 'passkeys';

    public function label(): string
    {
        return match ($this) {
            self::TwoFactorAuthentication => 'Two-Factor Authentication',
            self::RolesAndPermissions => 'Roles and Permissions',
            self::Otp => 'OTP (one-time password)',
            self::ForgotPassword => 'Forgot Password flow',
            self::GoogleSso => 'Google SSO',
            self::Passkeys => 'Passkeys (WebAuthn)',
        };
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(self::slugs(), array_map(
            fn (self $f) => $f->label(),
            self::cases()
        ));
    }
}
