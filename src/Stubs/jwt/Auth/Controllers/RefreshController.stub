<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Factory as JWTAuth;
use PHPOpenSourceSaver\JWTAuth\JWT;

class RefreshController
{
    public function __invoke(JWTAuth $jwtAuth, JWT $jwt): JsonResponse
    {
        return responder()
            ->success([
                'refreshToken' => $jwt->refresh(),
                'tokenType' => 'bearer',
                'expiresIn' => $jwtAuth->getTTL() * 60,
            ])
            ->respond();
    }
}
