<?php

declare(strict_types=1);

namespace Lightitlabs\Contracts;

interface SetupReporter
{
    public function written(string $path): void;

    /**
     * @param string $reason why it was left alone, completing "Skipped <path>: "
     */
    public function skipped(string $path, string $reason = 'the file already exists'): void;

    /**
     * @param string       $title   the step's bold title in the feature's checklist, word for word
     * @param string|null  $file    the file the step edits, relative to its project root
     * @param list<string> $details the full instructions, printed only with -v
     */
    public function manualStep(string $title, string|null $file = null, array $details = []): void;

    public function warning(string $message): void;

    /**
     * The feature keeps going but is reported as failed.
     *
     * @param list<string> $details output that explains the error, e.g. a failed command's
     */
    public function error(string $message, array $details = []): void;
}
