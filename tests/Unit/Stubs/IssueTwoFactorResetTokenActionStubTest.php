<?php

declare(strict_types=1);

/**
 * IssueTwoFactorResetTokenAction.stub becomes Eloquent code in the
 * consuming project (it calls the real `User::create2faToken()`), so it
 * cannot be exercised against a database from this package - see
 * laravel-package-testing: no Eloquent, no database here. This pins the
 * `challenge_ttl_minutes` fallback structurally: dropping the `, 15`
 * default (or changing it) fails this assertion, where a config-driven
 * behavioral test - always run against the default app config, which
 * already sets `challenge_ttl_minutes` to 15 - would not have noticed.
 */
describe('IssueTwoFactorResetTokenAction stub', function (): void {
    it('falls back to a 15 minute TTL when google2fa.challenge_ttl_minutes is not configured', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/IssueTwoFactorResetTokenAction.stub'
        );

        expect($stub)->toContain("Config::integer('google2fa.challenge_ttl_minutes', 15)");
    });
});
