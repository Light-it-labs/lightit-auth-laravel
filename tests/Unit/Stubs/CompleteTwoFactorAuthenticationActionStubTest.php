<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\CompleteTwoFactorAuthenticationAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeGoogle2FA;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeUser;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\IssueTwoFactorChallengeAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\LoginByUserAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\StubLoader;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\TwoFactorAuthException;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\VerifyOtpAction;

StubLoader::load(
    'Shared/Auth/TwoFactorAuthenticatable.stub',
    'Shared/Auth/Actions/IssueTwoFactorChallengeAction.stub',
    'Shared/Auth/Actions/LoginByUserAction.stub',
    'Google2FA/Auth/Exceptions/TwoFactorAuthException.stub',
    'Google2FA/Auth/TwoFactorAttemptLimiter.stub',
    'Google2FA/Auth/Actions/VerifyOtpAction.stub',
    'Google2FA/Auth/Actions/CompleteTwoFactorAuthenticationAction.stub',
);

/**
 * The lockout ordering is pinned structurally (TwoFactorAttemptLimiterStubTest
 * covers the limiter's own behaviour); the replay case runs the rendered action
 * against the in-memory fakes, with a real session guard.
 */
describe('CompleteTwoFactorAuthenticationAction stub', function (): void {
    it('atomically counts the attempt before verifying, and clears it on success', function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Google2FA/Auth/Actions/CompleteTwoFactorAuthenticationAction.stub'
        );

        expect($stub)->toContain('TwoFactorAttemptLimiter::ensureNotLockedOut($user->id);')
            ->and($stub)->toContain('TwoFactorAttemptLimiter::clear($user->id);')
            ->and($stub)->not->toContain('TwoFactorAttemptLimiter::recordFailure');
    });

    it('signs the user in once per code, and rejects the same code a second time', function (): void {
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $user = new FakeUser();
        $user->setRawAttributes([
            'id' => 'user-' . bin2hex(random_bytes(6)),
            'two_factor_auth_secret' => Crypt::encryptString('ACTIVE-SECRET'),
            'two_factor_auth_activated_at' => now(),
        ]);

        $request = Request::create('/2fa/complete', 'POST');
        $request->setLaravelSession(app('session')->driver('array'));
        $auth = app(AuthFactory::class);

        $action = new CompleteTwoFactorAuthenticationAction(
            new VerifyOtpAction(new FakeGoogle2FA(['123456' => 1000])),
            new LoginByUserAction($auth, $request, new IssueTwoFactorChallengeAction($auth, $request)),
        );

        $action->execute($user, '123456');

        expect($auth->guard('web')->id())->toBe($user->getKey());

        $auth->guard('web')->logout();

        try {
            $action->execute($user, '123456');
            test()->fail('Expected the replayed code to be rejected.');
        } catch (TwoFactorAuthException $exception) {
            expect($exception->statusCode())->toBe(422)
                ->and($exception->errorCode())->toBe('otp_already_used')
                ->and($auth->guard('web')->check())->toBeFalse();
        }
    });
});
