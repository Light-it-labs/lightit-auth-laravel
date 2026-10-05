<?php

declare(strict_types=1);

use Lightitlabs\Enums\Feature;

describe('Feature::selectable()', function (): void {
    it('offers two-factor authentication in auth:setup', function (): void {
        expect(Feature::selectable())->toContain(Feature::TwoFactorAuthentication);
    });

    it('offers passkeys in auth:setup', function (): void {
        expect(Feature::selectable())->toContain(Feature::Passkeys);
    });

    it('offers social login in auth:setup', function (): void {
        expect(Feature::selectable())->toContain(Feature::SocialLogin);
    });

    it('still withholds OTP', function (): void {
        expect(Feature::selectable())->not->toContain(Feature::Otp);
    });
});
