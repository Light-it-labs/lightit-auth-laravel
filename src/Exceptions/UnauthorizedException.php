<?php

declare(strict_types=1);

namespace App\Exceptions;

use Flugg\Responder\Exceptions\Http\HttpException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class UnauthorizedException extends HttpException
{
    protected $status = JsonResponse::HTTP_UNAUTHORIZED;
    protected $errorCode = 'unauthorized';

}
