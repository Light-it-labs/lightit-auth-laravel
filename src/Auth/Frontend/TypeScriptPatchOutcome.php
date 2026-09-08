<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

enum TypeScriptPatchOutcome
{
    case Patched;
    case AlreadyApplied;
    case AnchorNotFound;
    case Missing;
    case Failed;
    case Corrupted;

    public function needsManualStep(): bool
    {
        return $this !== self::Patched && $this !== self::AlreadyApplied;
    }
}
