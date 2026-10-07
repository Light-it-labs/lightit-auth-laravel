<?php

declare(strict_types=1);

namespace Lightitlabs\Contracts;

interface SetupReporter
{
    public function written(string $path): void;

    public function skipped(string $path): void;

    /**
     * @param list<string> $details lines printed verbatim under the instruction
     */
    public function manualStep(string $instruction, array $details = []): void;

    public function warning(string $message): void;
}
