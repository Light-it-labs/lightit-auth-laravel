<?php

declare(strict_types=1);

namespace Lightit\Shared\App\Exceptions\Http;

use Illuminate\Http\JsonResponse;

class InvalidGoogleTokenException extends HttpException
{
    /**
     * An HTTP status code.
     */
    protected int $status = JsonResponse::HTTP_BAD_REQUEST;

    /**
     * The error code.
     */
    protected string|null $errorCode = 'Invalid_google_token';
}
