<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Authentication\App\Requests\TwoFactorAuthenticationCodeRequest;
use Lightit\Authentication\Domain\Actions\VerifyTwoFactorAuthenticationAction;
use Lightit\Lightranet\Users\Domain\User;

class VerifyTwoFactorAuthenticationController
{
    public function __invoke(
        TwoFactorAuthenticationCodeRequest $request,
        VerifyTwoFactorAuthenticationAction $verifyTwoFactorAuthenticationAction,
    ): JsonResponse {
        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable  $user */
        $user = $request->user();

        $oneTimePassword = $request->getOtpCode();

        $user = $verifyTwoFactorAuthenticationAction->execute($user, $oneTimePassword);

        return responder()
            ->success($user)
            ->respond();
    }
}
