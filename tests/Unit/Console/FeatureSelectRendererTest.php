<?php

declare(strict_types=1);

use Laravel\Prompts\MultiSelectPrompt;
use Lightitlabs\Console\FeatureChoices;
use Lightitlabs\Console\FeatureSelectRenderer;
use Lightitlabs\Console\SetupTheme;
use Lightitlabs\Enums\Feature;

describe('FeatureSelectRenderer', function (): void {
    it('styles the feature list only when colour is on', function (bool $colors): void {
        $prompt = new MultiSelectPrompt(
            label: 'Select features',
            options: (new FeatureChoices(sys_get_temp_dir()))->options(Feature::selectable()),
        );

        $frame = SetupTheme::during($colors, static fn (): string => (new FeatureSelectRenderer($prompt))($prompt));

        expect(preg_match('/\e\[/', $frame))->toBe($colors ? 1 : 0)
            ->and($frame)->toContain('Two-Factor Authentication');
    })->with([
        'colour' => [true],
        'NO_COLOR or --no-ansi' => [false],
    ]);
});
