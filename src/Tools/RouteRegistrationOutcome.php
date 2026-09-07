<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

enum RouteRegistrationOutcome
{
    case Registered;
    case AlreadyRegistered;
    case ParentMissing;
    case Failed;
    case Corrupted;

    public function needsManualStep(): bool
    {
        return $this !== self::Registered && $this !== self::AlreadyRegistered;
    }
}
