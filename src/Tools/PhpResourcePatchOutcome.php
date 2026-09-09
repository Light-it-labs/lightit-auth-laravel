<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

enum PhpResourcePatchOutcome
{
    case Patched;
    case AlreadyApplied;
    case KeyAlreadyPresent;
    case AnchorNotFound;
    case Missing;
    case Failed;
    case Corrupted;

    public function needsManualStep(): bool
    {
        return $this !== self::Patched && $this !== self::AlreadyApplied;
    }
}
