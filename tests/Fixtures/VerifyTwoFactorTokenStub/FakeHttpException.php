<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;

use Exception;

/**
 * Stands in for `Lightit\Shared\App\Exceptions\Http\HttpException` when
 * `TwoFactorAuthException.stub` is rendered into this test namespace (see
 * VerifyTwoFactorTokenStubTest) - only the constructor shape and the two
 * properties the stub actually touches are reproduced.
 */
class FakeHttpException extends Exception
{
    protected int $status = 500;

    protected string $errorCode = '';

    public function __construct(string|null $message = null, array|null $headers = null)
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
