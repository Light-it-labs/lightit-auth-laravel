<?php

declare(strict_types=1);

/**
 * CompleteTwoFactorAuthenticationAction.stub becomes Eloquent code in the
 * consuming project (it persists the real `User` row), so it cannot be
 * exercised against a database from this package - see
 * laravel-package-testing: no Eloquent, no database here. These assertions
 * pin the per-user lockout structurally: TwoFactorAttemptLimiterStubTest
 * covers the limiter's own behaviour.
 */
describe('CompleteTwoFactorAuthenticationAction stub', function (): void {
    it('atomically counts the attempt before verifying, and clears it on success', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/CompleteTwoFactorAuthenticationAction.stub'
        );

        expect($stub)->toContain('TwoFactorAttemptLimiter::ensureNotLockedOut($user->getKey());')
            ->and($stub)->toContain('TwoFactorAttemptLimiter::clear($user->getKey());')
            ->and($stub)->not->toContain('TwoFactorAttemptLimiter::recordFailure');
    });
});
