<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\FakeUser;
use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\TwoFactorAuthException;
use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\TwoFactorReason;
use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\VerifyTwoFactorToken;

describe('Two-factor challenge token reason enforcement', function (): void {
    it('lets CompleteTwoFactorAuthenticationRequest accept a setup token (enrolment confirmation)', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Requests/CompleteTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain('$this->verifyTwoFactorToken->executeForAny(')
            ->and($stub)->toContain('TwoFactorReason::SetupRequired,')
            ->and($stub)->toContain('TwoFactorReason::VerificationRequired,');
    });

    it('does not let CompleteTwoFactorAuthenticationRequest accept a reset token', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Requests/CompleteTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->not->toContain('TwoFactorReason::ResetRequired');
    });

    it('binds VerifyRecoveryCodeRequest to a verification-required token', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Requests/VerifyRecoveryCodeRequest.stub'
        );

        expect($stub)->toContain(
            '$this->authenticatedUser = $this->verifyTwoFactorToken->execute($token, TwoFactorReason::VerificationRequired);'
        );
    });

    it('binds ResetTwoFactorAuthenticationRequest to a reset-required token, unchanged', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Requests/ResetTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain('TwoFactorReason::ResetRequired');
    });

    it('binds SetupTwoFactorAuthenticationRequest to a setup-required token only', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Requests/SetupTwoFactorAuthenticationRequest.stub'
        );

        expect($stub)->toContain(
            'return $this->verifyTwoFactorToken->execute($token, TwoFactorReason::SetupRequired);'
        )
            ->and($stub)->not->toContain('TwoFactorReason::VerificationRequired')
            ->and($stub)->not->toContain('TwoFactorReason::ResetRequired');
    });
});

/**
 * VerifyTwoFactorToken.stub is a template for the consuming app - it
 * hardcodes `Lightit\...` namespaces this package never loads directly, and
 * `User::query()->find()` needs a real Eloquent model this package has no
 * database to back. Rendered here into a private test namespace, the same
 * way IssueTwoFactorChallengeActionStubTest does, with FakeUser standing in as a plain
 * static registry instead of Eloquent - so `executeForAny()`'s reason
 * enforcement is exercised behaviorally instead of by grepping source text.
 */
function renderVerifyTwoFactorTokenStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/' . $relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain\Actions;',
            'namespace Lightit\Authentication\Domain\Enums;',
            'namespace Lightit\Authentication\Domain\Exceptions;',
            'namespace Lightit\Authentication\Domain\DataTransferObjects;',
            "use Lightit\Authentication\Domain\Enums\TwoFactorReason;\n",
            "use Lightit\Authentication\Domain\Exceptions\TwoFactorAuthException;\n",
            "use Lightit\Authentication\Domain\DataTransferObjects\TwoFactorTokenPayloadDto;\n",
            'use Lightit\Shared\App\Exceptions\Http\HttpException;',
            'use Lightit\Users\Domain\Models\User;',
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;',
            'namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;',
            'namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;',
            'namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;',
            '',
            '',
            '',
            'use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\FakeHttpException as HttpException;',
            'use Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub\FakeUser as User;',
        ],
        $contents,
    );
}

function requireRenderedVerifyTwoFactorTokenStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir() . '/verify-two-factor-token-stub-' . md5($relativePath) . '.php';
    file_put_contents($tempFile, renderVerifyTwoFactorTokenStub($relativePath));
    require_once $tempFile;
}

requireRenderedVerifyTwoFactorTokenStub('Shared/Auth/Enums/TwoFactorReason.stub');
requireRenderedVerifyTwoFactorTokenStub('Google2FA/Auth/DataTransferObjects/TwoFactorTokenPayloadDto.stub');
requireRenderedVerifyTwoFactorTokenStub('Google2FA/Auth/Exceptions/TwoFactorAuthException.stub');
requireRenderedVerifyTwoFactorTokenStub('Google2FA/Auth/Actions/VerifyTwoFactorToken.stub');

/**
 * @return array{sub:string, exp:int, typ:string, reason:string, mandatory:bool}
 */
function validTwoFactorPayload(string $reason, string $userId = 'user-1'): array
{
    return [
        'sub' => $userId,
        'exp' => now()->addMinutes(15)->timestamp,
        'typ' => '2fa',
        'reason' => $reason,
        'mandatory' => true,
    ];
}

describe('VerifyTwoFactorToken::executeForAny reason enforcement', function (): void {
    beforeEach(function (): void {
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        FakeUser::$registry['user-1'] = new FakeUser('user-1');
    });

    it('accepts a setup-required token', function (): void {
        $token = Crypt::encrypt(validTwoFactorPayload('setup_required'));

        $verifyTwoFactorToken = new VerifyTwoFactorToken();

        $user = $verifyTwoFactorToken->executeForAny(
            $token,
            TwoFactorReason::SetupRequired,
            TwoFactorReason::VerificationRequired,
        );

        expect($user->id)->toBe('user-1');
    });

    it('accepts a verification-required token', function (): void {
        $token = Crypt::encrypt(validTwoFactorPayload('verification_required'));

        $verifyTwoFactorToken = new VerifyTwoFactorToken();

        $user = $verifyTwoFactorToken->executeForAny(
            $token,
            TwoFactorReason::SetupRequired,
            TwoFactorReason::VerificationRequired,
        );

        expect($user->id)->toBe('user-1');
    });

    it('rejects a reset-required token when only setup or verification reasons are accepted', function (): void {
        $token = Crypt::encrypt(validTwoFactorPayload('reset_required'));

        $verifyTwoFactorToken = new VerifyTwoFactorToken();

        expect(fn () => $verifyTwoFactorToken->executeForAny(
            $token,
            TwoFactorReason::SetupRequired,
            TwoFactorReason::VerificationRequired,
        ))->toThrow(TwoFactorAuthException::class);
    });

    it('rejects an expired token', function (): void {
        $payload = validTwoFactorPayload('verification_required');
        $payload['exp'] = now()->subMinute()->timestamp;
        $token = Crypt::encrypt($payload);

        $verifyTwoFactorToken = new VerifyTwoFactorToken();

        expect(fn () => $verifyTwoFactorToken->executeForAny(
            $token,
            TwoFactorReason::VerificationRequired,
        ))->toThrow(TwoFactorAuthException::class);
    });
});
