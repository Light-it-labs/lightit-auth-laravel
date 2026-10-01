<?php

declare(strict_types=1);

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

describe('LightitServiceProvider passkeys rate limiter registration', function (): void {
    it('registers the passkeys limiter once PasskeyRateLimiter is installed', function (): void {
        $provider = new LightitServiceProvider($this->app);

        if (! class_exists('Lightit\Authentication\Domain\PasskeyRateLimiter', false)) {
            $provider->packageBooted();

            expect(RateLimiter::limiter('passkeys'))->toBeNull();

            $tempFile = sys_get_temp_dir() . '/lightit-passkey-rate-limiter-' . bin2hex(random_bytes(6)) . '.php';
            file_put_contents(
                $tempFile,
                (string) file_get_contents(__DIR__ . '/../../src/Stubs/Passkeys/Auth/PasskeyRateLimiter.stub')
            );
            require_once $tempFile;
            unlink($tempFile);
        }

        $provider->packageBooted();

        expect(RateLimiter::limiter('passkeys'))->not->toBeNull();
    });
});
