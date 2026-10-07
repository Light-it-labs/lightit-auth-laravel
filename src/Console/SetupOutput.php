<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Closure;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Spinner;
use Lightitlabs\Contracts\SetupReporter;
use Lightitlabs\Exceptions\SetupAbortedException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Everything `auth:setup` prints: installers only report events here, and what the user
 * must do by hand is held back for one summary at the end instead of scrolling away.
 */
final class SetupOutput implements SetupReporter
{
    private const MAX_WIDTH = 100;

    private const PACKAGE = 'lightit-auth-laravel';

    /**
     * @var list<FeatureReport>
     */
    private array $reports = [];

    private FeatureReport|null $current = null;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly ConsoleProfile $profile,
    ) {
    }

    public static function for(OutputInterface $output): self
    {
        return new self($output, ConsoleProfile::detect($output));
    }

    public function header(string $version, string $application, string|null $frontend): void
    {
        $this->frame(self::PACKAGE . ' ' . $version, [
            $this->row('back   ', $application, truncateFromStart: true),
            $frontend === null
                ? $this->row('front  ', 'none found, frontend steps are skipped', SetupTheme::GRAY)
                : $this->row('front  ', $frontend, truncateFromStart: true),
        ]);
    }

    /**
     * @param Closure(): mixed $install
     */
    public function feature(string $label, Closure $install, string|null $docs = null): bool
    {
        $report = new FeatureReport($label, $docs);
        $this->reports[] = $report;
        $this->current = $report;
        $failure = null;

        $run = function () use ($install, &$failure): void {
            try {
                $install();
            } catch (Throwable $exception) {
                $failure = $exception;
            } finally {
                // A nested artisan call that throws (optimize:clear) leaves prompts writing to
                // its NullOutput, and the spinner would then erase its last frame into nothing.
                Prompt::setOutput($this->output);
            }
        };

        $this->animates() ? (new Spinner("{$label}  installing…"))->spin($run) : $run();
        $this->current = null;

        $trace = null;

        if ($failure instanceof Throwable) {
            $report->failure = $failure->getMessage();
            $trace = $failure instanceof SetupAbortedException ? null : (string) $failure;
        }

        $this->line($this->status($report));

        if ($this->output->isVerbose()) {
            foreach ($report->written as $path) {
                $this->detail('Wrote ', $path, '');
            }

            foreach ($report->skipped as $path) {
                $this->detail('Skipped ', $path, ': the file already exists.');
            }

            if ($trace !== null) {
                $this->output->writeln(OutputFormatter::escape($trace));
            }
        }

        return ! $report->failed();
    }

    /**
     * @return bool whether every feature succeeded
     */
    public function summary(): bool
    {
        if ($this->reports === []) {
            $this->line('No features selected.');

            return true;
        }

        $rows = [];

        foreach ($this->reports as $report) {
            if ($rows !== []) {
                $rows[] = $this->row('', '');
            }

            array_push($rows, ...$this->section($report));
        }

        $this->output->writeln('');
        $this->frame('Summary', $rows);

        $failed = array_values(
            array_filter($this->reports, static fn (FeatureReport $report): bool => $report->failed())
        );

        if ($failed === []) {
            $this->line($this->paint(SetupTheme::LIME, '✔') . ' Authentication setup completed!');

            return true;
        }

        $labels = implode(', ', array_map(static fn (FeatureReport $report): string => $report->label, $failed));

        $this->wrapped(
            $this->paint(SetupTheme::RED, '✘') . ' ',
            "Authentication setup did not complete: {$labels} failed.",
        );
        $this->wrapped(
            '  ',
            'Fix the cause above and run php artisan auth:setup again with the same features. It is safe to re-run: '
            . 'files that already exist are reported as Skipped and never overwritten.',
        );

        return false;
    }

    public function written(string $path): void
    {
        $this->report()->written[] = $path;
    }

    public function skipped(string $path): void
    {
        $this->report()->skipped[] = $path;
    }

    public function manualStep(string $instruction, array $details = []): void
    {
        $this->report()->manualSteps[] = ['instruction' => $instruction, 'details' => $details];
    }

    public function warning(string $message): void
    {
        $this->report()->warnings[] = $message;
    }

    private function report(): FeatureReport
    {
        if ($this->current === null) {
            $this->current = new FeatureReport('Setup');
            $this->reports[] = $this->current;
        }

        return $this->current;
    }

    private function animates(): bool
    {
        return $this->profile->animated && $this->output->isDecorated() && \function_exists('pcntl_fork');
    }

    private function status(FeatureReport $report): string
    {
        if ($report->failed()) {
            return $this->fit(
                $this->paint(SetupTheme::RED, '✘') . ' <options=bold>' . $this->escape(
                    $report->label
                ) . '</> · failed'
            );
        }

        $warnings = \count($report->warnings);
        $counts = array_filter([
            \count($report->written) . ' created',
            $report->skipped === [] ? '' : \count($report->skipped) . ' skipped',
            match ($warnings) {
                0 => '',
                1 => '1 warning',
                default => "{$warnings} warnings",
            },
        ]);

        return $this->fit(
            $this->paint(SetupTheme::LIME, '✔') . ' <options=bold>' . $this->escape($report->label) . '</>'
            . $this->paint(SetupTheme::GRAY, ' · ' . implode(' · ', $counts))
        );
    }

    /**
     * @return list<array{lead: string, text: string, style: string|null, truncate: bool}>
     */
    private function section(FeatureReport $report): array
    {
        if ($report->failed()) {
            return [
                $this->row(
                    $this->paint(SetupTheme::RED, '✘') . ' ',
                    "{$report->label} setup failed: {$report->failure}",
                    'bold'
                ),
                $this->row('  ', 'The files it wrote before failing were kept. Re-running is safe.'),
                ...$this->warnings($report),
            ];
        }

        $rows = [$this->row($this->paint(SetupTheme::LIME, '✔') . ' ', $report->label, 'bold')];

        if ($report->manualSteps !== []) {
            $rows[] = $this->row('  ', 'Manual steps', SetupTheme::VIOLET);
        }

        foreach ($report->manualSteps as $number => $step) {
            $rows[] = $this->row(
                '  ' . $this->paint(SetupTheme::VIOLET, ($number + 1) . '.') . ' ',
                $step['instruction']
            );

            foreach ($step['details'] as $detail) {
                $rows[] = $this->row('       ', $detail);
            }
        }

        if ($report->checklists() !== []) {
            $rows[] = $this->row(
                '  ' . $this->paint(SetupTheme::VIOLET, 'Checklist') . ' ',
                implode(', ', $report->checklists())
            );
        }

        if ($report->docs !== null) {
            $rows[] = $this->row('  ' . $this->paint(SetupTheme::VIOLET, 'Docs') . '      ', $report->docs);
        }

        return [...$rows, ...$this->warnings($report)];
    }

    /**
     * @return list<array{lead: string, text: string, style: string|null, truncate: bool}>
     */
    private function warnings(FeatureReport $report): array
    {
        return array_map(
            fn (string $warning): array => $this->row(
                '  ' . $this->paint(SetupTheme::AMBER, '!') . ' ',
                $warning,
                SetupTheme::AMBER
            ),
            $report->warnings,
        );
    }

    /**
     * @return array{lead: string, text: string, style: string|null, truncate: bool}
     */
    private function row(string $lead, string $text, string|null $style = null, bool $truncateFromStart = false): array
    {
        return ['lead' => $lead, 'text' => $text, 'style' => $style, 'truncate' => $truncateFromStart];
    }

    /**
     * A box on a terminal; plain indented lines anywhere else.
     *
     * @param list<array{lead: string, text: string, style: string|null, truncate: bool}> $rows
     */
    private function frame(string $title, array $rows): void
    {
        if (! $this->profile->isTerminal()) {
            $this->line('<options=bold>' . $this->escape($title) . '</>');

            foreach ($rows as $row) {
                $this->line(rtrim('  ' . $row['lead'] . $this->styled($row['text'], $row['style'])));
            }

            return;
        }

        $width = $this->width();
        $inner = $width - 4;
        $edge = fn (string $text): string => $this->paint(SetupTheme::VIOLET, $text);
        $title = $this->clip($title, $inner - 2);

        $rule = str_repeat('─', max(0, $width - 5 - mb_strwidth($title)));
        $this->line($edge('┌─ ') . '<options=bold>' . $this->escape($title) . '</>' . $edge(" {$rule}┐"));

        foreach ($rows as $row) {
            foreach ($this->layout($row, $inner) as $line) {
                $padding = str_repeat(' ', max(0, $inner - $this->visibleWidth($line)));
                $this->line($edge('│') . " {$line}{$padding} " . $edge('│'));
            }
        }

        $this->line($edge('└' . str_repeat('─', $width - 2) . '┘'));
    }

    /**
     * Long text wraps under its own indent so nothing is lost; a path that has to stay on one
     * line keeps its end, which is the part that tells paths apart.
     *
     * @param array{lead: string, text: string, style: string|null, truncate: bool} $row
     *
     * @return list<string>
     */
    private function layout(array $row, int $width): array
    {
        $leadWidth = $this->visibleWidth($row['lead']);
        $room = max(10, $width - $leadWidth);

        if ($row['truncate']) {
            $text = mb_strwidth($row['text']) > $room ? '…' . mb_substr($row['text'], -($room - 1)) : $row['text'];

            return [$row['lead'] . $this->styled($text, $row['style'])];
        }

        $lines = [];

        foreach ($this->wrap($row['text'], $room) as $index => $chunk) {
            $lead = $index === 0 ? $row['lead'] : str_repeat(' ', $leadWidth);
            $lines[] = $lead . $this->styled($chunk, $row['style']);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $indent = (string) preg_replace('/^(\s*).*$/s', '$1', $text);
        $indent = mb_strwidth($indent) < intdiv($width, 2) ? $indent : '';
        $lines = [];
        $current = '';

        foreach (preg_split('/(?<=\s)(?=\S)/', $text) ?: [] as $word) {
            if ($current !== '' && mb_strwidth(rtrim($current . $word)) > $width) {
                $lines[] = rtrim($current);
                $current = $indent;
            }

            $current .= $word;

            while (mb_strwidth($current) > $width) {
                $lines[] = mb_strimwidth($current, 0, $width);
                $current = $indent . mb_substr($current, mb_strlen(mb_strimwidth($current, 0, $width)));
            }
        }

        $lines[] = rtrim($current);

        return $lines;
    }

    private function wrapped(string $lead, string $text): void
    {
        if (! $this->profile->isTerminal()) {
            $this->line($lead . $this->escape($text));

            return;
        }

        foreach ($this->layout($this->row($lead, $text), $this->width()) as $line) {
            $this->line($line);
        }
    }

    /**
     * One line per file even on a narrow terminal: the start of a long path goes first.
     */
    private function detail(string $verb, string $path, string $suffix): void
    {
        $room = $this->profile->isTerminal() ? $this->width() - 4 - mb_strwidth($verb . $suffix) : null;

        if ($room !== null && mb_strwidth($path) > $room) {
            $path = '…' . mb_substr($path, -max(1, $room - 1));
        }

        $this->line('    ' . $this->paint(SetupTheme::GRAY, $verb . $path . $suffix));
    }

    private function fit(string $line): string
    {
        if (! $this->profile->isTerminal() || $this->visibleWidth($line) <= $this->width()) {
            return $line;
        }

        $plain = Helper::removeDecoration($this->output->getFormatter(), $line);

        return $this->escape($this->clip($plain, $this->width()));
    }

    private function clip(string $text, int $width): string
    {
        return mb_strwidth($text) <= $width ? $text : mb_strimwidth($text, 0, $width - 1) . '…';
    }

    private function width(): int
    {
        return min(self::MAX_WIDTH, max(20, $this->profile->width ?? self::MAX_WIDTH));
    }

    private function styled(string $text, string|null $style): string
    {
        return match (true) {
            $style === null || $text === '' => $this->escape($text),
            $style === 'bold' => '<options=bold>' . $this->escape($text) . '</>',
            default => $this->paint($style, $text),
        };
    }

    private function paint(string $hex, string $text): string
    {
        return "<fg={$hex}>" . $this->escape($text) . '</>';
    }

    private function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }

    private function visibleWidth(string $line): int
    {
        return Helper::width(Helper::removeDecoration($this->output->getFormatter(), $line));
    }

    private function line(string $line): void
    {
        $this->output->writeln($line);
    }
}
