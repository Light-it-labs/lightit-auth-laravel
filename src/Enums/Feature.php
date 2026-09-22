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
     * Two-factor authentication, OTP and Google SSO still emit Bearer-shaped code
     * whose driver this package no longer installs, so they are withheld until the
     * session-based login rewires them.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [
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
