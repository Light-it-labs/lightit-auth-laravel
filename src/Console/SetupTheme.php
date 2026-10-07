<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Closure;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Spinner;
use Symfony\Component\Console\Color;

/**
 * Light-it violet for frames and accents, lime for what succeeded. Symfony degrades the hex
 * colours to the 8 ANSI ones on terminals without truecolor.
 */
final class SetupTheme
{
    public const VIOLET = '#9B7BFF';

    public const LIME = '#D6ED18';

    public const RED = '#F2777A';

    public const AMBER = '#F5C451';

    public const GRAY = '#8A8F98';

    private const NAME = 'lightit-auth-setup';

    private static bool $colors = true;

    /**
     * The consumer's app may have its own prompts theme, so ours only lives for this call.
     * Prompts paints regardless of NO_COLOR and --no-ansi, so the caller says whether to.
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public static function during(bool $colors, Closure $callback): mixed
    {
        $previousColors = self::$colors;
        self::$colors = $colors;

        Prompt::addTheme(self::NAME, [
            MultiSelectPrompt::class => FeatureSelectRenderer::class,
            Spinner::class => SetupSpinnerRenderer::class,
        ]);

        $previous = Prompt::theme();
        Prompt::theme(self::NAME);

        try {
            return $callback();
        } finally {
            Prompt::theme($previous);
            self::$colors = $previousColors;
        }
    }

    public static function paint(string $hex, string $text): string
    {
        if (! self::$colors) {
            return $text;
        }

        return (new Color($hex))->apply($text);
    }
}
