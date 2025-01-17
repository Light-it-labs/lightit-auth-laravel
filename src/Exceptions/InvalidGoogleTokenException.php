<?php

declare(strict_types=1);

namespace Lightit\Exceptions;

use Flugg\Responder\Exceptions\Http\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class InvalidGoogleTokenException extends HttpException
{
    protected $status = Response::HTTP_BAD_REQUEST;
    protected $errorCode = 'Invalid_google_token_exception';

}
