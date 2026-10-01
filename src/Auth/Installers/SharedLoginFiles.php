<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

/**
 * Every login-adjacent feature (2FA, OTP, Google SSO, passkeys) routes through
 * the same challenge action, so each of their installers writes the same set of
 * shared stubs - kept in one place instead of a separately maintained copy of
 * this map per installer.
 */
final class SharedLoginFiles
{
    public const FILES = [
        '/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
        '/Actions/IssueTwoFactorChallengeAction.stub' => 'Domain/Actions/IssueTwoFactorChallengeAction.php',
        '/Enums/TwoFactorReason.stub' => 'Domain/Enums/TwoFactorReason.php',
        '/Exceptions/TwoFactorChallengeException.stub' => 'Domain/Exceptions/TwoFactorChallengeException.php',
        '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
        '/DataTransferObjects/TwoFactorTokenPayloadDto.stub' => 'Domain/DataTransferObjects/TwoFactorTokenPayloadDto.php',
    ];

    public static function stubsPath(): string
    {
        return __DIR__ . '/../../Stubs/Shared/Auth';
    }
}
