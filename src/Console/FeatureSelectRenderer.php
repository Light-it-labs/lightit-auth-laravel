<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Themes\Default\MultiSelectPromptRenderer;

/**
 * Draws each {@see FeatureChoices} label as a name column, a dimmed description and an
 * "installed" tag, instead of one long line.
 */
final class FeatureSelectRenderer extends MultiSelectPromptRenderer
{
    public function cyan(string $text): string
    {
        return SetupTheme::paint(SetupTheme::VIOLET, $text);
    }

    public function gray(string $text): string
    {
        return SetupTheme::paint(SetupTheme::GRAY, $text);
    }

    protected function renderOptions(MultiSelectPrompt $prompt): string
    {
        $width = $prompt->terminal()->cols() - 12;
        $nameWidth = max(0, ...array_map(
            static fn (int|string $label): int => mb_strwidth(FeatureChoices::parse((string) $label)['name']),
            array_values($prompt->options),
        ));
        $values = array_is_list($prompt->options) ? $prompt->options : array_keys($prompt->options);
        $keys = array_keys($prompt->options);
        $visible = $prompt->visible();

        $lines = array_map(
            function (int|string $key) use ($prompt, $visible, $values, $keys, $nameWidth, $width): string {
                $index = (int) array_search($key, $keys, true);
                $active = $index === $prompt->highlighted;
                $selected = \in_array($values[$index], $prompt->value(), true);
    
                return match (true) {
                    $active && $selected => $this->cyan('› ◼') . ' ',
                    $active => $this->cyan('›') . ' ◻ ',
                    $selected => '  ' . $this->cyan('◼') . ' ',
                    default => '  ' . $this->dim('◻') . ' ',
                } . $this->option((string) $visible[$key], $nameWidth, $width) . '  ';
            },
            array_keys($visible)
        );

        return implode(PHP_EOL, $this->scrollbar(
            $lines,
            $prompt->firstVisible,
            $prompt->scroll,
            \count($prompt->options),
            min($this->longest($prompt->options, padding: 6), $prompt->terminal()->cols() - 6),
        ));
    }

    protected function renderSelectedOptions(MultiSelectPrompt $prompt): string
    {
        $names = array_map(
            static fn (int|string $label): string => FeatureChoices::parse((string) $label)['name'],
            $prompt->labels(),
        );

        return $names === [] ? $this->gray('None') : implode(', ', $names);
    }

    private function option(string $label, int $nameWidth, int $width): string
    {
        $parts = FeatureChoices::parse($label);
        $tag = $parts['installed'] ? 'installed' : '';
        $room = max(0, $width - $nameWidth - mb_strwidth($tag) - 4);
        $description = $room > 1 && $parts['description'] !== '' ? $this->truncate($parts['description'], $room) : '';

        return $this->pad($parts['name'], $nameWidth)
            . '  ' . $this->pad($this->dim($description), $room)
            . ($tag === '' ? '' : '  ' . SetupTheme::paint(SetupTheme::LIME, $tag));
    }
}
