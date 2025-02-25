<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Authentication\App\Requests\TwoFactorAuthenticationCodeRequest;
use Lightit\Authentication\Domain\Actions\DisableTwoFactorAuthenticationAction;

class DisableTwoFactorAuthenticationController
{
    public function __invoke(
        TwoFactorAuthenticationCodeRequest $request,
        DisableTwoFactorAuthenticationAction $disableTwoFactorAuthenticationAction,
    ): JsonResponse {
        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable $user */
        $user = $request->user();

        $oneTimePassword = $request
            ->string($request::ONE_TIME_PASSWORD)
            ->toString();

        $disableTwoFactorAuthenticationAction->execute($user, $oneTimePassword);

        return responder()
            ->success(['message' => 'Successfully disabled two-factor authentication.'])
            ->respond();
    }
}
