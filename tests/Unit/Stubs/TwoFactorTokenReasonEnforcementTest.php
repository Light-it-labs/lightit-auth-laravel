<?php

declare(strict_types=1);

describe('Two-factor challenge token reason enforcement', function (): void {
    it('lets CompleteTwoFactorAuthenticationRequest accept a setup token (enrolment confirmation)', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Requests/CompleteTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain('$this->verifyTwoFactorToken->executeForAny(')
            ->and($stub)->toContain('TwoFactorReason::SetupRequired,')
            ->and($stub)->toContain('TwoFactorReason::VerificationRequired,');
    });

    it('does not let CompleteTwoFactorAuthenticationRequest accept a reset token', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Requests/CompleteTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->not->toContain('TwoFactorReason::ResetRequired');
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

    it('lets VerifyTwoFactorToken::executeForAny accept a token matching any of the given reasons', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Actions/VerifyTwoFactorToken.stub'
        );

        expect($stub)->toContain('public function executeForAny(string $token, TwoFactorReason ...$expected): User');
    });

    it('rejects a reset-required token when the request only accepts setup or verification reasons', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Google2FA/Auth/Actions/VerifyTwoFactorToken.stub'
        );

        expect($stub)->toContain("reason: 'invalid_token_reason'");
    });
});
