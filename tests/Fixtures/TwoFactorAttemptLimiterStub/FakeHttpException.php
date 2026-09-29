<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub;

use Exception;

/**
 * Stands in for `Lightit\Shared\App\Exceptions\Http\HttpException` when
 * `TwoFactorAuthException.stub` is rendered into this test namespace (see
 * TwoFactorAttemptLimiterStubTest) - only the constructor shape and the
 * status this test actually reads are reproduced.
 */
class FakeHttpException extends Exception
{
    protected int $status = 500;

    protected string $errorCode = '';

    public function __construct(?string $message = null, ?array $headers = null)
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
