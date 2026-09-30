<?php

declare(strict_types=1);

use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeGoogle2FA;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\StubLoader;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\TwoFactorAuthException;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\VerifyOtpAction;

StubLoader::load(
    'Google2FA/Auth/Exceptions/TwoFactorAuthException.stub',
    'Google2FA/Auth/Actions/VerifyOtpAction.stub',
);

function verifyOtpFailure(callable $attempt): TwoFactorAuthException
{
    try {
        $attempt();
    } catch (TwoFactorAuthException $exception) {
        return $exception;
    }

    test()->fail('Expected a TwoFactorAuthException to be thrown.');
}

describe('VerifyOtpAction stub', function (): void {
    beforeEach(function (): void {
        $this->action = new VerifyOtpAction(new FakeGoogle2FA(['123456' => 1000, '654321' => 1001]));
        $this->userId = 'user-' . bin2hex(random_bytes(6));
    });

    it('accepts a valid code', function (): void {
        $this->action->execute($this->userId, 'SECRET', '123456');
    })->throwsNoExceptions();

    it('rejects a wrong code with 422 invalid_otp', function (): void {
        $exception = verifyOtpFailure(fn () => $this->action->execute($this->userId, 'SECRET', '000000'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp');
    });

    it('rejects the same code a second time with 422 otp_already_used', function (): void {
        $this->action->execute($this->userId, 'SECRET', '123456');

        $exception = verifyOtpFailure(fn () => $this->action->execute($this->userId, 'SECRET', '123456'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('otp_already_used');
    });

    it('still accepts the code of a later timestep after one was used', function (): void {
        $this->action->execute($this->userId, 'SECRET', '123456');
        $this->action->execute($this->userId, 'SECRET', '654321');
    })->throwsNoExceptions();

    it('does not block another user who submits a code for the same timestep', function (): void {
        $this->action->execute($this->userId, 'SECRET', '123456');
        $this->action->execute('other-' . $this->userId, 'OTHER-SECRET', '123456');
    })->throwsNoExceptions();

    it('keeps the used-code claim for the whole window the code is valid in, then drops it', function (): void {
        $this->action->execute($this->userId, 'SECRET', '123456');

        $this->travel(89)->seconds();
        expect(verifyOtpFailure(fn () => $this->action->execute($this->userId, 'SECRET', '123456'))->errorCode())
            ->toBe('otp_already_used');

        $this->travel(2)->seconds();
        $this->action->execute($this->userId, 'SECRET', '123456');
    });
});
