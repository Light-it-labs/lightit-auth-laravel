<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

/**
 * Every login-adjacent feature (2FA, OTP, Google SSO) routes through the same
 * challenge action, so all three installers must write the same set of
 * shared stubs - kept in one place instead of three separately maintained
 * copies of this map.
 */
final class SharedLoginFiles
{
    /**
     * @var array<string, string>
     */
    public const FILES = [
        '/Actions/LoginByUserAction.stub' => 'Domain/Actions/LoginByUserAction.php',
        '/Actions/IssueTwoFactorChallengeAction.stub' => 'Domain/Actions/IssueTwoFactorChallengeAction.php',
        '/Enums/TwoFactorReason.stub' => 'Domain/Enums/TwoFactorReason.php',
        '/Exceptions/TwoFactorChallengeException.stub' => 'Domain/Exceptions/TwoFactorChallengeException.php',
        '/TwoFactorAuthenticatable.stub' => 'Domain/TwoFactorAuthenticatable.php',
    ];

    public static function stubsPath(): string
    {
        return __DIR__.'/../../Stubs/Shared/Auth';
    }
}
