<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

use Laravel\Prompts\Spinner;
use Laravel\Prompts\Themes\Default\Renderer;

final class SetupSpinnerRenderer extends Renderer
{
    private const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private const INTERVAL_MS = 80;

    public function __invoke(Spinner $spinner): string
    {
        $spinner->interval = self::INTERVAL_MS;

        $frame = $spinner->static ? '…' : self::FRAMES[$spinner->count % \count(self::FRAMES)];

        return (string) $this->line(' ' . SetupTheme::paint(SetupTheme::VIOLET, $frame) . ' ' . $spinner->message);
    }
}
