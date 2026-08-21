<?php

declare(strict_types=1);

use Lightitlabs\Enums\AuthDriver;

describe('AuthDriver', function (): void {
    it('resolves the sanctum cookie driver from its cli slug', function (): void {
        expect(AuthDriver::from('sanctum-cookie'))->toBe(AuthDriver::SanctumCookie);
    });

    it('resolves every driver from its cli slug', function (): void {
        expect(AuthDriver::slugs())->toBe(['jwt', 'sanctum-token', 'sanctum-cookie', 'google-sso']);
    });

    it('maps slugs to display labels', function (): void {
        expect(AuthDriver::options())->toBe([
            'jwt' => 'JWT',
            'sanctum-token' => 'Sanctum API Token',
            'sanctum-cookie' => 'Sanctum Cookie (SPA)',
            'google-sso' => 'Google SSO',
        ]);
    });

    it('keys options by slug and values by label', function (): void {
        expect(array_keys(AuthDriver::options()))->toBe(AuthDriver::slugs())
            ->and(array_values(AuthDriver::options()))
            ->toBe(array_map(static fn (AuthDriver $driver): string => $driver->label(), AuthDriver::cases()));
    });

    it('rejects an unknown slug', function (): void {
        expect(AuthDriver::tryFrom('sanctum_cookie'))->toBeNull();
    });
});
