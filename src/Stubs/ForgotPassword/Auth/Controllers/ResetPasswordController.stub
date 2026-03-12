<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Authentication\App\Requests\ResetPasswordRequest;
use Lightit\Authentication\Domain\Actions\ResetPasswordAction;

final readonly class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordAction $resetPasswordAction): JsonResponse
    {
        $resetPasswordAction->execute($request->toDto());

        return response()->json([
            'data' => [
                'message' => __('passwords.reset'),
            ],
        ]);
    }
}
