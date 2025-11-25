<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Lightit\Authentication\App\Requests\TwoFactorAuthenticationCodeRequest;
use Lightit\Authentication\Domain\Actions\EnableTwoFactorAuthenticationAction;
use Lightit\Users\App\Resources\UserResource;

final readonly class EnableTwoFactorAuthenticationController
{
    public function __invoke(
        TwoFactorAuthenticationCodeRequest $request,
        EnableTwoFactorAuthenticationAction $enableTwoFactorAuthenticationAction,
    ): JsonResponse {
        $guardName = Config::string('google2fa.guard');

        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable  $user */
        $user = $request->user($guardName);

        $oneTimePassword = $request->getOtpCode();

        $user = $enableTwoFactorAuthenticationAction->execute($user, $oneTimePassword);

        return UserResource::make($user)
            ->response();
    }
}
