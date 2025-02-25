<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use PragmaRX\Google2FALaravel\Google2FA;

class GenerateQRCodeAction
{
    public function __construct(
        private readonly Google2FA $google2FA,
    ) {
    }

    public function execute(string $holderIdentifier, string $secret): string
    {
        return $this
            ->google2FA
            ->getQRCodeInline((string) config('app.name'), $holderIdentifier, $secret);
    }
}
