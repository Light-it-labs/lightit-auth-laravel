<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Lightit\Authentication\Domain\TwoFactorAuthenticatable;

class VerifyTwoFactorAuthenticationAction
{
    public function __construct(
        private readonly VerifyTwoFactorAuthenticationOTPAction $verifyTwoFactorAuthenticationOTPAction,
    ) {
    }

    public function execute(TwoFactorAuthenticatable $user, string $oneTimePassword): TwoFactorAuthenticatable
    {
        $this->verifyTwoFactorAuthenticationOTPAction->execute($user->getTwoFactorAuthSecret(), $oneTimePassword);

        $user->update([
            TwoFactorAuthenticatable::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME => now(),
        ]);

        return $user;
    }
}
