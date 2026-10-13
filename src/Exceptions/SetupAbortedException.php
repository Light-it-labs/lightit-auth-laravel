<?php

declare(strict_types=1);

namespace Lightitlabs\Exceptions;

use RuntimeException;

final class SetupAbortedException extends RuntimeException
{
    public static function corruptedRouteFile(string $routeFile, string $requireStatement): self
    {
        return new self(
            "{$routeFile} was left in an inconsistent state while adding {$requireStatement}. Please inspect the file."
        );
    }
}
