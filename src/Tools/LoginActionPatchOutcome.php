<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

enum LoginActionPatchOutcome
{
    case Installed;
    case Patched;
    case AlreadyApplied;
    case CustomizedSkipped;
    case SourceMissing;
    case Failed;

    public function needsManualStep(): bool
    {
        return $this !== self::Installed && $this !== self::Patched && $this !== self::AlreadyApplied;
    }
}
