<?php

declare(strict_types=1);

/**
 * VerifyRecoveryCodeAction.stub (through ConsumeRecoveryCodeAction.stub) becomes Eloquent code in the consuming
 * project (it locks and persists the real `User` row), so it cannot be
 * exercised against a database from this package - see
 * laravel-package-testing: no Eloquent, no database here. These assertions
 * pin the atomicity fix structurally: read, match and consume must all run
 * inside the same locked transaction.
 */
describe('VerifyRecoveryCodeAction stub', function (): void {
    it('locks and consumes the recovery code inside a single transaction', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/ConsumeRecoveryCodeAction.stub'
        );

        expect($stub)->toContain('DB::transaction(static function ()')
            ->and($stub)->toContain('->lockForUpdate()')
            ->and($stub)->toContain('replaceRecoveryCodes(array_values($hashedCodes))');
    });

    it('atomically counts the attempt before verifying, and clears it on success', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/VerifyRecoveryCodeAction.stub'
        );

        expect($stub)->toContain('TwoFactorAttemptLimiter::ensureNotLockedOut($user->id);')
            ->and($stub)->toContain('TwoFactorAttemptLimiter::clear($user->id);')
            ->and($stub)->not->toContain('TwoFactorAttemptLimiter::recordFailure');
    });
});
