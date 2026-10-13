<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Terminal;

/**
 * Without a terminal (CI, a pipe, a test) the width is null: lines are never wrapped or
 * truncated and nothing is boxed or animated, so logs stay plain and stable.
 */
final class ConsoleProfile
{
    public function __construct(
        public readonly int|null $width,
        public readonly bool $animated = false,
    ) {
    }

    public static function detect(OutputInterface $output): self
    {
        $stream = $output instanceof OutputStyle ? $output->getOutput() : $output;

        if (! $stream instanceof StreamOutput || ! stream_isatty($stream->getStream())) {
            return new self(null);
        }

        return new self((new Terminal())->getWidth(), animated: true);
    }

    public function isTerminal(): bool
    {
        return $this->width !== null;
    }
}
