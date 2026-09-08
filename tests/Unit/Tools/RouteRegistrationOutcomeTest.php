<?php

declare(strict_types=1);

use Lightitlabs\Tools\RouteRegistrationOutcome;

describe('RouteRegistrationOutcome', function (): void {
    it('needs a manual step for every outcome but a clean registration', function (): void {
        expect(RouteRegistrationOutcome::Registered->needsManualStep())->toBeFalse()
            ->and(RouteRegistrationOutcome::AlreadyRegistered->needsManualStep())->toBeFalse()
            ->and(RouteRegistrationOutcome::ParentMissing->needsManualStep())->toBeTrue()
            ->and(RouteRegistrationOutcome::Failed->needsManualStep())->toBeTrue()
            ->and(RouteRegistrationOutcome::Corrupted->needsManualStep())->toBeTrue();
    });
});
