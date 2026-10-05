<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthExceptionStub;

use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;

/**
 * Mirrors the constructor of laravel-for-package's `Lightit\Shared\App\Exceptions\Http\HttpException`:
 * it hands `$this->status` to Symfony's exception, so a subclass has to set the status first.
 */
abstract class HttpException extends BaseHttpException
{
    protected int $status = 500;

    protected string $errorCode;

    /**
     * @param array<string, string>|null $headers
     */
    public function __construct(string|null $message = null, array|null $headers = null)
    {
        parent::__construct($this->status, $message ?? '', null, $headers ?? []);
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
