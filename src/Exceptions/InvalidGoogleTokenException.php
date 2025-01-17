<?php

declare(strict_types=1);

namespace Lightit\Exceptions;

use Flugg\Responder\Exceptions\Http\HttpException;
use Illuminate\Http\JsonResponse;

class InvalidGoogleTokenException extends HttpException
{
    protected $status = JsonResponse::HTTP_BAD_REQUEST;

    protected $errorCode = 'Invalid_google_token';

}
