<?php

declare(strict_types=1);

use Lightitlabs\Enums\Feature;

describe('Feature::selectable()', function (): void {
    it('offers two-factor authentication in auth:setup', function (): void {
        expect(Feature::selectable())->toContain(Feature::TwoFactorAuthentication);
    });

    it('still withholds OTP and Google SSO', function (): void {
        expect(Feature::selectable())
            ->not->toContain(Feature::Otp)
            ->not->toContain(Feature::GoogleSso);
    });
});
