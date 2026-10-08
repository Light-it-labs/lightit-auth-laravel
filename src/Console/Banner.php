<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

/**
 * The Light-it lockup from the AI stack installer (Light-it-labs/ai-setup-tui-back,
 * app/Themes/Logo.php): violet bolt, "light-it" wordmark and their ░ drop shadow, unchanged.
 */
final class Banner
{
    public const NAME = 'light-it';

    /**
     * Narrowest terminal that still gets art: the bolt alone.
     */
    public const WIDTH = 60;

    /**
     * Narrowest terminal that fits the bolt and the wordmark side by side.
     */
    public const LOCKUP_WIDTH = 79;

    private const VIOLET = '#794DFC';

    private const INDENT = '  ';

    private const ICON_WIDTH = 13;

    private const GAP = '   ';

    private const ICON = [
        '    ▄█',
        '  ▄███░',
        '▄█████░',
        '██████▄▄▄▄▄▄',
        '████████████░',
        '████████████░',
        '▀▀▀▀▀▀██████░',
        '      █████▀░',
        '      ███▀░░',
        '      █▀░░',
        '       ░',
    ];

    private const WORDMARK = [
        '███   ███               ███         ███          ███   ███',
        '███░   ░░░              ███░        ███░          ░░░  ███░',
        '███░  ███    ▄█████████ █████████▄  █████        ███   █████',
        '███░  ███░  ███▀▀▀▀████░████▀▀▀▀███ ███▀▀░       ███░  ███▀▀░',
        '███░  ███░ ███     ▀███░███░░   ███░███  ███████ ███░  ███░',
        '███░  ███░ ▀███   ▄████░███░    ███░███  ▀▀▀▀▀▀▀░███░  ███░',
        '▀████ ▀████ ▀██████████░███░    ███░█████        █████ ▀████',
        '  ▀▀▀░  ▀▀▀░  ░▀▀▀▀▄███░ ▀▀░    ▀▀░░ ░▀▀▀░        ░▀▀▀░  ▀▀▀░',
        '             ▄█▄▄▄▄███░░',
        '             ▀██████▀░░',
        '               ░░░░░░',
    ];

    /**
     * The wordmark has no colour so it reads on light and dark terminals alike; below
     * {@see self::LOCKUP_WIDTH} columns only the bolt is drawn.
     *
     * @return list<string> console-formatted lines
     */
    public static function art(int $width): array
    {
        $withWordmark = $width >= self::LOCKUP_WIDTH;
        $lines = [];

        foreach (self::ICON as $row => $icon) {
            $line = self::INDENT . '<fg=' . self::VIOLET . ';options=bold>' . self::shade($icon) . '</>';

            if ($withWordmark) {
                $line .= str_repeat(' ', self::ICON_WIDTH - mb_strwidth($icon)) . self::GAP
                    . '<options=bold>' . self::shade(self::WORDMARK[$row]) . '</>';
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private static function shade(string $line): string
    {
        return (string) preg_replace('/░+/u', '<fg=' . SetupTheme::GRAY . '>$0</>', $line);
    }
}
