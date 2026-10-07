<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Contracts\SetupReporter;

final class RecordingSetupReporter implements SetupReporter
{
    /**
     * @var list<string>
     */
    public array $warnings = [];

    public function written(string $path): void
    {
    }

    public function skipped(string $path): void
    {
    }

    public function manualStep(string $instruction, array $details = []): void
    {
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
