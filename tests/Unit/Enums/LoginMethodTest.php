<?php

declare(strict_types=1);

use Lightitlabs\Enums\LoginMethod;

describe('LoginMethod', function (): void {
    it('resolves google sso from its cli slug', function (): void {
        expect(LoginMethod::from('google-sso'))->toBe(LoginMethod::GoogleSso);
    });

    it('resolves every login method from its slug', function (): void {
        expect(LoginMethod::slugs())->toBe(['password', 'google-sso']);
    });

    it('maps slugs to display labels', function (): void {
        expect(LoginMethod::options())->toBe([
            'password' => 'Password',
            'google-sso' => 'Google SSO',
        ]);
    });

    it('keys options by slug and values by label', function (): void {
        expect(array_keys(LoginMethod::options()))->toBe(LoginMethod::slugs())
            ->and(array_values(LoginMethod::options()))
            ->toBe(array_map(static fn (LoginMethod $method): string => $method->label(), LoginMethod::cases()));
    });

    it('rejects an unknown slug', function (): void {
        expect(LoginMethod::tryFrom('sso'))->toBeNull();
    });
});
