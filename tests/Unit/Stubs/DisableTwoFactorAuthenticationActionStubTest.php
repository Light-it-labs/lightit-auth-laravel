<?php

declare(strict_types=1);

/**
 * DisableTwoFactorAuthenticationAction.stub becomes Eloquent code in the
 * consuming project (it calls PasswordValidatorAction and
 * ResetTwoFactorAuthenticationAction against a real `User` row), so it
 * cannot be exercised against a database from this package - see
 * laravel-package-testing: no Eloquent, no database here. This pins the
 * `mandatory` fallback structurally: dropping the `, true` default (or
 * flipping it) fails this assertion, where a config-driven behavioral test
 * - always run against the default app config, which already sets
 * `mandatory` to true - would not have noticed.
 */
describe('DisableTwoFactorAuthenticationAction stub', function (): void {
    it(
        'falls back to mandatory=true (2FA cannot be disabled) when google2fa.mandatory is not configured',
        function (): void {
            $stub = (string) file_get_contents(
                __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/DisableTwoFactorAuthenticationAction.stub'
            );
    
            expect($stub)->toContain("Config::boolean('google2fa.mandatory', true)");
        }
    );
});
