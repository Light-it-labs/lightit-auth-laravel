<?php

declare(strict_types=1);

use Lightitlabs\Tools\PackageVersion;

describe('PackageVersion', function (): void {
    it('resolves a non-empty version string for the real package', function (): void {
        $version = (new PackageVersion)->resolve();

        expect($version)->toBeString()
            ->and($version)->not->toBe('');
    });

    it('falls back to unknown when the package is not registered', function (): void {
        $version = (new PackageVersion('light-it-labs/does-not-exist'))->resolve();

        expect($version)->toBe('unknown');
    });
});
