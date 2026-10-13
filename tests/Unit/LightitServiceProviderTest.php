<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Lightitlabs\LightitServiceProvider;

/**
 * The `2fa` rate limiter `throttle:2fa` needs is registered by the package's
 * own provider on boot, not by the routes stub at file scope, so it still
 * runs under `route:cache` (Laravel never executes cached route files).
 * `Lightit\Authentication\Domain\TwoFactorRateLimiter` only exists once a
 * consumer runs `auth:setup` with 2FA selected, so the provider guards the
 * call behind `class_exists()`.
 */
describe('LightitServiceProvider 2fa rate limiter registration', function (): void {
    it(
        'does nothing when boot runs before TwoFactorRateLimiter is installed, then registers the limiter once it exists',
        function (): void {
            expect(class_exists('Lightit\Authentication\Domain\TwoFactorRateLimiter', false))->toBeFalse();
    
            $provider = new LightitServiceProvider($this->app);
    
            $provider->packageBooted();
    
            expect(RateLimiter::limiter('2fa'))->toBeNull();
    
            $tempFile = sys_get_temp_dir() . '/lightit-service-provider-two-factor-rate-limiter.php';
            file_put_contents(
                $tempFile,
                (string) file_get_contents(__DIR__ . '/../../src/Stubs/Google2FA/Auth/TwoFactorRateLimiter.stub')
            );
            require_once $tempFile;
    
            expect(class_exists('Lightit\Authentication\Domain\TwoFactorRateLimiter', false))->toBeTrue();
    
            $provider->packageBooted();
    
            expect(RateLimiter::limiter('2fa'))->not->toBeNull();
        }
    );
});

function loadPasskeyRateLimiterStub(): void
{
    if (class_exists('Lightit\Authentication\Domain\PasskeyRateLimiter', false)) {
        return;
    }

    $tempFile = sys_get_temp_dir() . '/lightit-passkey-rate-limiter-' . bin2hex(random_bytes(6)) . '.php';
    file_put_contents(
        $tempFile,
        (string) file_get_contents(__DIR__ . '/../../src/Stubs/Passkeys/Auth/PasskeyRateLimiter.stub')
    );
    require_once $tempFile;
    unlink($tempFile);
}

describe('LightitServiceProvider passkeys rate limiter registration', function (): void {
    it('registers the passkeys and passkey sign-in limiters once PasskeyRateLimiter is installed', function (): void {
        $provider = new LightitServiceProvider($this->app);

        if (! class_exists('Lightit\Authentication\Domain\PasskeyRateLimiter', false)) {
            $provider->packageBooted();

            expect(RateLimiter::limiter('passkeys'))->toBeNull();

            loadPasskeyRateLimiterStub();
        }

        $provider->packageBooted();

        expect(RateLimiter::limiter('passkeys'))->not->toBeNull();

        $signIn = RateLimiter::limiter('passkeys-sign-in');
        $limit = $signIn === null ? null : $signIn(Request::create(
            '/',
            'POST',
            server: ['REMOTE_ADDR' => '203.0.113.7']
        ));

        expect($limit)->toBeInstanceOf(Limit::class)
            ->and($limit?->maxAttempts)->toBe(20)
            ->and($limit?->key)->toBe('passkeys-sign-in|203.0.113.7')
            ->and(RateLimiter::limiter('passkeys-sign-in-options'))->not->toBeNull();
    });

    it(
        'gives the sign-in options and the sign-in their own bucket, so spending one leaves the other',
        function (): void {
            loadPasskeyRateLimiterStub();
            (new LightitServiceProvider($this->app))->packageBooted();
    
            $throttle = app(ThrottleRequests::class);
            $send = static fn (string $limiter): Response => $throttle->handle(
                Request::create('/', 'POST', server: ['REMOTE_ADDR' => '198.51.100.4']),
                static fn (): Response => new Response('ok'),
                $limiter,
            );
    
            for ($attempt = 1; $attempt <= 20; $attempt++) {
                $send('passkeys-sign-in-options');
            }
    
            expect(fn () => $send('passkeys-sign-in-options'))->toThrow(ThrottleRequestsException::class)
                ->and($send('passkeys-sign-in')->getStatusCode())->toBe(200);
        }
    );
});

describe('LightitServiceProvider social login rate limiter registration', function (): void {
    it('registers a per-IP social-login limiter once SocialLoginRateLimiter is installed', function (): void {
        $provider = new LightitServiceProvider($this->app);

        if (! class_exists('Lightit\Authentication\Domain\SocialLoginRateLimiter', false)) {
            $provider->packageBooted();

            expect(RateLimiter::limiter('social-login'))->toBeNull();

            $tempFile = sys_get_temp_dir() . '/lightit-social-login-rate-limiter-' . bin2hex(random_bytes(6)) . '.php';
            file_put_contents(
                $tempFile,
                (string) file_get_contents(__DIR__ . '/../../src/Stubs/SocialLogin/Auth/SocialLoginRateLimiter.stub')
            );
            require_once $tempFile;
            unlink($tempFile);
        }

        $provider->packageBooted();

        $limiter = RateLimiter::limiter('social-login');
        $limit = $limiter === null ? null : $limiter(Request::create(
            '/',
            'POST',
            server: ['REMOTE_ADDR' => '203.0.113.7']
        ));

        expect($limit)->toBeInstanceOf(Limit::class)
            ->and($limit?->maxAttempts)->toBe(10)
            ->and($limit?->key)->toBe('social-login|203.0.113.7');
    });
});
