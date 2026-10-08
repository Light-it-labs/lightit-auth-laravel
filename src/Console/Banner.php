<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

/**
 * The Light-it lockup (violet bolt + "light-it" wordmark), downsampled from the AI stack
 * installer's logo to half its height so it fits a 60-column terminal.
 */
final class Banner
{
    public const NAME = 'light-it';

    public const WIDTH = 60;

    private const VIOLET = '#794DFC';

    private const ICON_COLUMN = 14;

    private const ICON = [
        '    ▄█',
        '  ▄███▄▄▄▄▄▄',
        '████████████░',
        '▀▀▀▀▀▀███▀░',
        '      █▀░',
    ];

    private const WORDMARK = [
        '██  ▀▀           ██       ██       ▀▀  ██',
        '██░ ██░ ▄██████  ██████▄  ███░     ██░ ███░',
        '██░ ██░ ██   ██░ ██░  ██░ ██░ ███▀ ██░ ██░',
        '██▄ ██▄ ▀██████░ ██░  ██░ ██▄  ░░░ ██▄ ██▄',
        ' ░░  ░░ ▄▄▄▄██▀░  ░    ░   ░░       ░░  ░░',
    ];

    /**
     * The wordmark has no colour so it reads on light and dark terminals alike.
     *
     * @return list<string> console-formatted lines
     */
    public static function art(): array
    {
        $lines = [];

        foreach (self::ICON as $row => $icon) {
            $padding = str_repeat(' ', self::ICON_COLUMN - mb_strwidth($icon));

            $lines[] = '  <fg=' . self::VIOLET . ';options=bold>' . self::shade($icon) . '</>' . $padding
                . '<options=bold>' . self::shade(self::WORDMARK[$row]) . '</>';
        }

        return $lines;
    }

    private static function shade(string $line): string
    {
        return (string) preg_replace('/░+/u', '<fg=' . SetupTheme::GRAY . '>$0</>', $line);
    }
}
