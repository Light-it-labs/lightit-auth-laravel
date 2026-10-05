<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Lightitlabs\Tests\Fixtures\TwoFactorRateLimiterStub\TwoFactorRateLimiter;

/**
 * TwoFactorRateLimiter.stub is a template for the consuming app - it
 * hardcodes `Lightit\...` namespaces this package never loads directly.
 * Rendered here into a private test namespace, the same way
 * IssueTwoFactorChallengeActionStubTest does, so its per-token/per-IP limiter keys are
 * exercised without a full consumer app.
 */
function renderTwoFactorRateLimiterStub(): string
{
    $contents = (string) file_get_contents(
        __DIR__ . '/../../../src/Stubs/Google2FA/Auth/TwoFactorRateLimiter.stub'
    );

    return str_replace(
        'namespace Lightit\Authentication\Domain;',
        'namespace Lightitlabs\Tests\Fixtures\TwoFactorRateLimiterStub;',
        $contents,
    );
}

$tempFile = sys_get_temp_dir() . '/two-factor-rate-limiter-stub.php';
file_put_contents($tempFile, renderTwoFactorRateLimiterStub());
require_once $tempFile;

describe('TwoFactorRateLimiter stub', function (): void {
    it('keys the token bucket by the hashed bearer token, not by the request IP alone', function (): void {
        TwoFactorRateLimiter::register();

        $limiter = RateLimiterFacade::limiter('2fa');
        expect($limiter)->not->toBeNull();

        $requestA = Request::create('/2fa/complete', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $requestA->headers->set('Authorization', 'Bearer token-a');

        $requestB = Request::create('/2fa/complete', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $requestB->headers->set('Authorization', 'Bearer token-b');

        $limitsA = (array) call_user_func($limiter, $requestA);
        $limitsB = (array) call_user_func($limiter, $requestB);

        expect($limitsA)->toHaveCount(2);

        $tokenKeyA = $limitsA[0]->key;
        $tokenKeyB = $limitsB[0]->key;

        expect($tokenKeyA)->not->toBe($tokenKeyB);
        expect($tokenKeyA)->not->toContain('token-a');
    });

    it('also applies a per-IP limit shared across different tokens behind the same address', function (): void {
        TwoFactorRateLimiter::register();

        $limiter = RateLimiterFacade::limiter('2fa');

        $requestA = Request::create('/2fa/complete', 'POST', server: ['REMOTE_ADDR' => '10.0.0.9']);
        $requestA->headers->set('Authorization', 'Bearer token-a');

        $requestB = Request::create('/2fa/complete', 'POST', server: ['REMOTE_ADDR' => '10.0.0.9']);
        $requestB->headers->set('Authorization', 'Bearer token-b');

        $limitsA = (array) call_user_func($limiter, $requestA);
        $limitsB = (array) call_user_func($limiter, $requestB);

        expect($limitsA[1]->key)->toBe($limitsB[1]->key);
    });

    it('keys a session request without a token by the signed-in user, not one bucket for everyone', function (): void {
        TwoFactorRateLimiter::register();

        $limiter = RateLimiterFacade::limiter('2fa');

        $sessionRequest = static function (int $userId): Request {
            $request = Request::create('/2fa/confirm', 'POST', server: ['REMOTE_ADDR' => '10.0.0.7']);
            $request->setUserResolver(static fn (): GenericUser => new GenericUser(['id' => $userId]));

            return $request;
        };

        $limitsA = (array) call_user_func($limiter, $sessionRequest(1));
        $limitsB = (array) call_user_func($limiter, $sessionRequest(2));

        expect($limitsA[0]->key)->toBe('2fa-user|1')
            ->and($limitsB[0]->key)->toBe('2fa-user|2');
    });

    it('keys a signed-in user by id even when the request also carries a bearer token', function (): void {
        TwoFactorRateLimiter::register();

        $request = Request::create('/2fa/enable', 'POST', server: ['REMOTE_ADDR' => '10.0.0.7']);
        $request->headers->set('Authorization', 'Bearer junk');
        $request->setUserResolver(static fn (): GenericUser => new GenericUser(['id' => 42]));

        $limits = (array) call_user_func(RateLimiterFacade::limiter('2fa'), $request);

        expect($limits[0]->key)->toBe('2fa-user|42');
    });

    it('throttles a signed-in user on the sixth request even with a new junk bearer each time', function (): void {
        TwoFactorRateLimiter::register();

        $throttle = app(ThrottleRequests::class);

        $sendWithFreshJunkToken = static function (int $attempt) use ($throttle): void {
            $request = Request::create('/2fa/disable', 'POST', server: ['REMOTE_ADDR' => '10.0.0.8']);
            $request->headers->set('Authorization', "Bearer junk-{$attempt}");
            $request->setUserResolver(static fn (): GenericUser => new GenericUser(['id' => 77]));

            $throttle->handle($request, static fn (): Response => new Response('ok'), '2fa');
        };

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $sendWithFreshJunkToken($attempt);
        }

        expect(fn () => $sendWithFreshJunkToken(6))->toThrow(ThrottleRequestsException::class);
    });
});
