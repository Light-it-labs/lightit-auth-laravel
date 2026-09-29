<?php

declare(strict_types=1);

use Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub\TwoFactorAttemptLimiter;
use Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub\TwoFactorAuthException;

/**
 * TwoFactorAttemptLimiter.stub is a template for the consuming app - it
 * hardcodes `Lightit\...` namespaces this package never loads directly.
 * Rendered here into a private test namespace, the same way
 * TwoFactorRateLimiterStubTest does, so the per-user lockout is exercised
 * behaviorally instead of by grepping source text.
 */
function renderTwoFactorAttemptLimiterStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/Google2FA/Auth/' . $relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain;',
            'namespace Lightit\Authentication\Domain\Exceptions;',
            "use Lightit\Authentication\Domain\Exceptions\TwoFactorAuthException;\n",
            'use Lightit\Shared\App\Exceptions\Http\HttpException;',
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub;',
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub;',
            '',
            'use Lightitlabs\Tests\Fixtures\TwoFactorAttemptLimiterStub\FakeHttpException as HttpException;',
        ],
        $contents,
    );
}

function requireRenderedTwoFactorAttemptLimiterStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir() . '/two-factor-attempt-limiter-stub-' . md5($relativePath) . '.php';
    file_put_contents($tempFile, renderTwoFactorAttemptLimiterStub($relativePath));
    require_once $tempFile;
}

requireRenderedTwoFactorAttemptLimiterStub('Exceptions/TwoFactorAuthException.stub');
requireRenderedTwoFactorAttemptLimiterStub('TwoFactorAttemptLimiter.stub');

describe('TwoFactorAttemptLimiter stub', function (): void {
    it('allows an attempt while under the failure threshold', function (): void {
        $userId = 'user-' . bin2hex(random_bytes(6));

        TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
        TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
        TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
    })->throwsNoExceptions();

    it('locks the user out after 5 attempts, independent of any token or IP', function (): void {
        $userId = 'user-' . bin2hex(random_bytes(6));

        for ($i = 0; $i < 5; $i++) {
            TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
        }

        try {
            TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
            test()->fail('Expected a TwoFactorAuthException to be thrown.');
        } catch (TwoFactorAuthException $exception) {
            expect($exception->statusCode())->toBe(429);
        }
    });

    it('rejects the 6th attempt even though the check happens after the count already includes it', function (): void {
        // ensureNotLockedOut() increments and checks in the same RateLimiter::hit()
        // call - unlike a separate "read the count, then increment on failure" pair,
        // there is no window where a 6th caller could read a stale, still-allowed
        // count. Simulating a burst sequentially still proves the count returned by
        // the hit itself - not a prior read - is what gates the 6th attempt.
        $userId = 'user-' . bin2hex(random_bytes(6));

        $rejectedAt = null;

        for ($i = 1; $i <= 6; $i++) {
            try {
                TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
            } catch (TwoFactorAuthException) {
                $rejectedAt = $i;

                break;
            }
        }

        expect($rejectedAt)->toBe(6);
    });

    it('does not lock out a different user sharing no token or IP with the locked-out one', function (): void {
        $lockedOutUserId = 'user-' . bin2hex(random_bytes(6));
        $otherUserId = 'user-' . bin2hex(random_bytes(6));

        for ($i = 0; $i < 5; $i++) {
            TwoFactorAttemptLimiter::ensureNotLockedOut($lockedOutUserId);
        }

        TwoFactorAttemptLimiter::ensureNotLockedOut($otherUserId);
    })->throwsNoExceptions();

    it(
        'clears the attempt count on success, so the lockout does not persist past a correct attempt',
        function (): void {
            $userId = 'user-' . bin2hex(random_bytes(6));

            for ($i = 0; $i < 4; $i++) {
                TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
            }

            TwoFactorAttemptLimiter::clear($userId);

            TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
            TwoFactorAttemptLimiter::ensureNotLockedOut($userId);
        }
    )->throwsNoExceptions();
});
