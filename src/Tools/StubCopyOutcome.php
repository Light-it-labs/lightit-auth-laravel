<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

enum StubCopyOutcome: string
{
    case Written = 'written';
    case Skipped = 'skipped';
}
