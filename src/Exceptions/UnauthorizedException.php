<?php

declare(strict_types=1);

namespace Lightit\Exceptions;

use Flugg\Responder\Exceptions\Http\HttpException;
use Illuminate\Http\JsonResponse;

class UnauthorizedException extends HttpException
{
    protected $status = JsonResponse::HTTP_UNAUTHORIZED;

    protected $errorCode = 'unauthorized';
}
