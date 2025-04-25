<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Authentication\App\Requests\TwoFactorAuthenticationCodeRequest;
use Lightit\Authentication\Domain\Actions\EnableTwoFactorAuthenticationAction;
use Lightit\Backoffice\Users\App\Resources\UserResource;

final readonly class EnableTwoFactorAuthenticationController
{
    public function __invoke(
        TwoFactorAuthenticationCodeRequest $request,
        EnableTwoFactorAuthenticationAction $enableTwoFactorAuthenticationAction,
    ): JsonResponse {
        $guardName = config('google2fa.guard');

        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable  $user */
        $user = $request->user($guardName);

        $oneTimePassword = $request->getOtpCode();

        $user = $enableTwoFactorAuthenticationAction->execute($user, $oneTimePassword);

        return UserResource::make($user)
            ->response();
    }
}
