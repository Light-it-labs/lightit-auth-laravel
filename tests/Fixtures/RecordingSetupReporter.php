<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Contracts\SetupReporter;

final class RecordingSetupReporter implements SetupReporter
{
    /**
     * @var list<array{title: string, file: string|null, details: list<string>}>
     */
    public array $manualSteps = [];

    /**
     * @var list<string>
     */
    public array $warnings = [];

    /**
     * @var list<string>
     */
    public array $errors = [];

    public function written(string $path): void
    {
    }

    public function skipped(string $path): void
    {
    }

    public function manualStep(string $title, string|null $file = null, array $details = []): void
    {
        $this->manualSteps[] = ['title' => $title, 'file' => $file, 'details' => $details];
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message, array $details = []): void
    {
        $this->errors[] = $message;
    }
}
