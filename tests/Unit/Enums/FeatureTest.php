<?php

declare(strict_types=1);

use Lightitlabs\Enums\Feature;

describe('Feature', function (): void {
    it('resolves every feature from its slug', function (): void {
        expect(Feature::slugs())->toBe([
            'two-factor-authentication',
            'roles-and-permissions',
            'otp',
            'forgot-password',
            'passkeys',
        ]);
    });

    it('maps slugs to display labels', function (): void {
        expect(Feature::options())->toBe([
            'two-factor-authentication' => 'Two-Factor Authentication',
            'roles-and-permissions' => 'Roles and Permissions',
            'otp' => 'OTP (one-time password)',
            'forgot-password' => 'Forgot Password flow',
            'passkeys' => 'Passkeys (WebAuthn)',
        ]);
    });

    it('keys options by slug and values by label', function (): void {
        expect(array_keys(Feature::options()))->toBe(Feature::slugs())
            ->and(array_values(Feature::options()))
            ->toBe(array_map(static fn (Feature $feature): string => $feature->label(), Feature::cases()));
    });

    it('rejects an unknown slug', function (): void {
        expect(Feature::tryFrom('bogus'))->toBeNull();
    });
});
