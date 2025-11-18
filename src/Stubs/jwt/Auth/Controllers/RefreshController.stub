<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Authentication\App\Resources\LoginResource;
use Lightit\Authentication\Domain\DataTransferObjects\LoginDto;
use PHPOpenSourceSaver\JWTAuth\Factory as JWTAuth;
use PHPOpenSourceSaver\JWTAuth\JWT;

class RefreshController
{
    public function __invoke(JWTAuth $jwtAuth, JWT $jwt): JsonResponse
    {
        $loginDto = new LoginDto(
            accessToken: $jwt->refresh(),
            tokenType: 'Bearer',
            expiresIn: $jwtAuth->getTTL() * 60,
        );

        return LoginResource::make($loginDto)
            ->response();
    }
}
