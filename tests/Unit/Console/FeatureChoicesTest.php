<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Console\FeatureChoices;
use Lightitlabs\Enums\Feature;

describe('FeatureChoices', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/lightit-feature-choices-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root . '/config');
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('describes every selectable feature in one line and tags the ones already installed', function (): void {
        touch($this->root . '/config/google2fa.php');

        $options = (new FeatureChoices($this->root))->options(Feature::selectable());

        expect(array_keys($options))->toBe(['two-factor-authentication', 'roles-and-permissions', 'forgot-password'])
            ->and(FeatureChoices::parse($options['two-factor-authentication']))->toMatchArray([
                'name' => 'Two-Factor Authentication',
                'installed' => true,
            ])
            ->and(FeatureChoices::parse($options['roles-and-permissions'])['installed'])->toBeFalse();

        foreach ($options as $label) {
            expect(FeatureChoices::parse($label)['description'])->not->toBe('')->not->toContain("\n");
        }
    });

    it('points every feature at a page that exists in docs/', function (Feature $feature): void {
        $page = basename(FeatureChoices::docs($feature));

        expect(dirname(__DIR__, 3) . '/docs/' . $page)->toBeFile();
    })->with(Feature::cases());
});
