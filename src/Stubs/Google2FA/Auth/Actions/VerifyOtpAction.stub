<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Lightit\Shared\App\Exceptions\Http\UnauthorizedException;
use PragmaRX\Google2FALaravel\Google2FA;

final readonly class VerifyOtpAction
{
    public function __construct(
        private Google2FA $google2FA,
    ) {
    }

    public function execute(string $secret, string $oneTimePassword): void
    {
        if (! $this->google2FA->verifyKey($secret, $oneTimePassword)) {
            throw new UnauthorizedException(__('google2fa.invalid_otp'));
        }
    }
}
