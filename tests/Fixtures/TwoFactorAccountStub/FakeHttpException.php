<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

use Exception;

/**
 * Stands in for `Lightit\Shared\App\Exceptions\Http\HttpException` - only the
 * constructor shape and the two accessors the tests read are reproduced.
 */
class FakeHttpException extends Exception
{
    protected int $status = 500;

    protected string $errorCode = '';

    public function __construct(string|null $message = null)
    {
        parent::__construct($message ?? '');
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
