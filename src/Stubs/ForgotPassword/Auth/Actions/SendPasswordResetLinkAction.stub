<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Illuminate\Support\Facades\Password;

final readonly class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}
