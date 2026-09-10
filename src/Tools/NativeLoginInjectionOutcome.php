<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

enum NativeLoginInjectionOutcome
{
    case Patched;
    case AlreadyApplied;
    case AnchorNotFound;
    case Failed;
    case Corrupted;

    public function needsManualStep(): bool
    {
        return $this !== self::Patched && $this !== self::AlreadyApplied;
    }
}
