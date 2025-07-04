<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Lightit\Authentication\Domain\DataTransferObjects\TwoFactorSetupDto;
use Lightit\Authentication\Domain\TwoFactorAuthenticatable;
use PragmaRX\Google2FALaravel\Google2FA;

final readonly class SetupTwoFactorAuthenticationAction
{
    public function __construct(
        private Google2FA $google2FA,
        private GenerateQRCodeAction $generateQRCodeAction,
    ) {
    }

    public function execute(TwoFactorAuthenticatable $user): TwoFactorSetupDto
    {
        $secret = $this
            ->google2FA
            ->generateSecretKey();

        $user->update([
            TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME => $secret,
        ]);

        $qr = $this->generateQRCodeAction->execute($user->getAuthenticatableHolderIdentifier(), $secret);

        return new TwoFactorSetupDto($qr, $secret);
    }
}
