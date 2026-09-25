<?php

declare(strict_types=1);

describe('Two-factor challenge token reason enforcement', function (): void {
    it('binds CompleteTwoFactorAuthenticationRequest to a verification-required token', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Requests/CompleteTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain('$this->verifyTwoFactorToken->execute($token, TwoFactorReason::VerificationRequired)');
    });

    it('binds VerifyRecoveryCodeRequest to a verification-required token', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Requests/VerifyRecoveryCodeRequest.stub'
        );

        expect($stub)->toContain(
            '$this->authenticatedUser = $this->verifyTwoFactorToken->execute($token, TwoFactorReason::VerificationRequired);'
        );
    });

    it('binds ResetTwoFactorAuthenticationRequest to a reset-required token, unchanged', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Requests/ResetTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain('TwoFactorReason::ResetRequired');
    });
});
