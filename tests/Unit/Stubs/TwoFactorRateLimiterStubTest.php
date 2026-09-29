<?php

declare(strict_types=1);

use Illuminate\Http\Request;
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
});
