<?php

declare(strict_types=1);

/**
 * The frontend's axios interceptor redirects every 401 to /login, which
 * loses the 2FA challenge in progress. A wrong code is the user's mistake,
 * not an authentication failure, so it must answer 422; an invalid or
 * expired challenge token is what actually ends the challenge, so it stays
 * the default 401 `TwoFactorAuthException` already throws.
 */
describe('Wrong-code vs. invalid-token status codes', function (): void {
    it('answers a wrong one-time password with 422', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/VerifyOtpAction.stub'
        );

        expect($stub)->toContain(
            "throw new TwoFactorAuthException(message: __('google2fa.invalid_otp'), reason: 'invalid_otp', status: 422);"
        );
    });

    it('answers a wrong recovery code with 422', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/VerifyRecoveryCodeAction.stub'
        );

        expect($stub)->toContain(
            "throw new TwoFactorAuthException(message: __('google2fa.invalid_recovery_code'), reason: 'invalid_recovery_code', status: 422);"
        );
    });

    it('leaves an invalid or expired challenge token at the default 401', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/VerifyTwoFactorToken.stub'
        );

        expect($stub)->not->toContain('status: 422');

        $exceptionStub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Exceptions/TwoFactorAuthException.stub'
        );

        expect($exceptionStub)->toContain('int $status = 401');
    });

    it('hands the status it was given to the HTTP exception it extends, not the default 401', function (): void {
        $rendered = str_replace(
            [
                'namespace Lightit\\Authentication\\Domain\\Exceptions;',
                "use Lightit\\Shared\\App\\Exceptions\\Http\\HttpException;\n",
            ],
            ['namespace Lightitlabs\\Tests\\Fixtures\\TwoFactorAuthExceptionStub;', ''],
            (string) file_get_contents(
                __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Exceptions/TwoFactorAuthException.stub'
            ),
        );
        $tempFile = sys_get_temp_dir() . '/two-factor-auth-exception-stub-' . md5($rendered) . '.php';
        file_put_contents($tempFile, $rendered);
        require_once $tempFile;

        $exception = new Lightitlabs\Tests\Fixtures\TwoFactorAuthExceptionStub\TwoFactorAuthException(
            message: 'Invalid 2FA OTP.',
            reason: 'invalid_otp',
            status: 422,
        );

        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp');
    });
});
