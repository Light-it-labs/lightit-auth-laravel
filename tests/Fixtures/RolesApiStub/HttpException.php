<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\RolesApiStub;

use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;

/**
 * The boilerplate's `Lightit\Shared\App\Exceptions\Http\HttpException`, trimmed to what
 * the roles exceptions and the boilerplate's JSON error rendering use.
 */
abstract class HttpException extends BaseHttpException
{
    protected int $status = 500;

    protected string $errorCode;

    public function __construct(string|null $message = null)
    {
        parent::__construct($this->status, $message ?? '');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
