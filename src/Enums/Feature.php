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

    /**
     * The features `auth:setup` offers.
     *
     * OTP is withheld until it has a frontend, a route file and a checklist; Google SSO
     * is being replaced by social login.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [
            self::TwoFactorAuthentication,
            self::RolesAndPermissions,
            self::ForgotPassword,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::TwoFactorAuthentication => 'Two-Factor Authentication',
            self::RolesAndPermissions => 'Roles and Permissions',
            self::Otp => 'OTP (one-time password)',
            self::ForgotPassword => 'Forgot Password',
            self::GoogleSso => 'Google SSO',
        };
    }
}
