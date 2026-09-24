<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Illuminate\Console\Command;

trait LightitConsoleOutput
{
    protected Command $command;

    public function initializeOutput(Command $command): void
    {
        $this->command = $command;
    }

    public function printSectionSeparator(): void
    {
        $this->command->line(
            "\e[0;35m──────────────────────────────────────────────────────────────\e[0m"
        );
    }

    public function printBoxedMessage(string $message): void
    {
        $length = mb_strlen($message) + 4;
        $top = '╭'.str_repeat('─', $length).'╮';
        $bottom = '╰'.str_repeat('─', $length).'╯';
        $middle = '│  '.$message.'  │';

        $this->command->line('');
        $this->command->line("\e[0;35m{$top}\e[0m");
        $this->command->line("\e[0;35m{$middle}\e[0m");
        $this->command->line("\e[0;35m{$bottom}\e[0m");
        $this->command->line('');
    }

    public function printStep(int $current, int $total, string $title): void
    {
        $message = "🪜 Step {$current}/{$total}: {$title}";
        $this->printBoxedMessage($message);
    }

    public function printSuccess(string $message): void
    {
        $this->printBoxedMessage('🚀 '.$message);
    }

    public function printFailure(string $message): void
    {
        $this->printBoxedMessage('❌ '.$message);
    }

    public function printFileCreated(string $message): void
    {
        $this->command->line("\e[0;35m📄 {$message}\e[0m");
    }

    public function printConfigPublished(string $message): void
    {
        $this->command->line("\e[0;35m🔧 {$message}\e[0m");
    }

    public function printMigrationCreated(string $message): void
    {
        $this->command->line("\e[0;35m🗄️ {$message}\e[0m");
    }

    public function printMiddlewareCreated(string $message): void
    {
        $this->command->line("\e[0;35m🛡️ {$message}\e[0m");
    }

    public function printSkipped(string $label): void
    {
        $this->command->line("\e[0;33m⏭️ Skipped {$label}: the file already exists.\e[0m");
    }
}
