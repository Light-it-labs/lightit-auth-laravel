<?php

declare(strict_types=1);

namespace Lightitlabs\Enums;

enum Feature: string
{
    case TwoFactorAuthentication = 'two-factor-authentication';
    case RolesAndPermissions = 'roles-and-permissions';
    case Otp = 'otp';
    case ForgotPassword = 'forgot-password';
    case SocialLogin = 'social-login';
    case Passkeys = 'passkeys';

    /**
     * The features `auth:setup` offers.
     *
     * OTP still emits Bearer-shaped code whose driver this package no longer installs,
     * so it is withheld until the session-based login rewires it.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [
            self::TwoFactorAuthentication,
            self::RolesAndPermissions,
            self::ForgotPassword,
            self::SocialLogin,
            self::Passkeys,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::TwoFactorAuthentication => 'Two-Factor Authentication',
            self::RolesAndPermissions => 'Roles and Permissions',
            self::Otp => 'OTP (one-time password)',
            self::ForgotPassword => 'Forgot Password',
            self::SocialLogin => 'Social Login (Google)',
            self::Passkeys => 'Passkeys',
        };
    }
}
