<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Lightit\Authentication\Domain\TwoFactorAuthenticatable;
use PragmaRX\Google2FALaravel\Google2FA;

class EnableTwoFactorAuthenticationAction
{
    public function __construct(
        private readonly Google2FA $google2FA,
        private readonly GenerateQRCodeAction $generateQRCodeAction,
    ) {
    }

    public function execute(TwoFactorAuthenticatable $user): string
    {
        $secret = $this
            ->google2FA
            ->generateSecretKey();

        $user->update([
            TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME => $secret,
        ]);

        return $this->generateQRCodeAction->execute($user->getAuthenticatableHolderIdentifier(), $secret);
    }
}
