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

    it('links every feature to its docs/ page on GitHub, since docs/ is not shipped to consumers', function (
        Feature $feature,
    ): void {
        $url = FeatureChoices::docs($feature);

        expect($url)->toStartWith('https://github.com/Light-it-labs/lightit-auth-laravel/blob/main/docs/')
            ->and(dirname(__DIR__, 3) . '/docs/' . basename($url))->toBeFile();
    })->with(Feature::cases());

    it('uses the same docs URL the 2FA checklist links to', function (): void {
        $checklist = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Stubs/Google2FA/AUTH-2FA-TODO.md.stub');

        expect($checklist)->toContain(FeatureChoices::docs(Feature::TwoFactorAuthentication));
    });
});
