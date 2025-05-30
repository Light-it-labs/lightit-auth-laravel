<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lightit\Authentication\Domain\Actions\SetupTwoFactorAuthenticationAction;

final readonly class SetupTwoFactorAuthenticationController
{
    public function __invoke(
        Request $request,
        SetupTwoFactorAuthenticationAction $setupTwoFactorAuthenticationAction,
    ): JsonResponse {
        $guardName = config('google2fa.guard');

        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable $user */
        $user = $request->user($guardName);

        $setupData = $setupTwoFactorAuthenticationAction->execute($user);

        return response()->json([
            'data' => [
                'qr' => $setupData->qr,
                'secret' => $setupData->secret,
            ],
        ]);
    }
}
