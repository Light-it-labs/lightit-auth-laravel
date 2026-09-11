<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\FakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserNotExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * `TwoFactorLoginGate::guardAgainstChallenge()` - wired into every login via
 * the generated pipeline or `NativeLoginActionInjector` - calls methods that
 * only exist on `TwoFactorAuthenticatable`. `config/google2fa.php`'s
 * `enabled` and `mandatory` both default to `true`, so a consumer's User
 * model that doesn't extend it turns every login into a
 * `BadMethodCallException` with no config change required. This exercises
 * the guard directly, since `Google2FAInstaller::install()` itself shells
 * out to `composer require` and is not something a unit test should run.
 */
function invokeUserModelGuard(string $userModelClass, string $requiredParentClass): void
{
    $installer = new Google2FAInstaller(
        new class extends Command
        {
            protected $signature = 'google2fa-user-model-guard-test';
        },
        new ComposerInstaller(new class extends Command
        {
            protected $signature = 'google2fa-user-model-guard-test-composer';
        }),
        new StubCopier(new OriginMarker('0.0.0-test')),
    );

    $guard = new ReflectionMethod($installer, 'ensureUserModelSupportsTwoFactor');
    $guard->setAccessible(true);
    $guard->invoke($installer, $userModelClass, $requiredParentClass);
}

describe('Google2FAInstaller refuses to wire 2FA into a User model that cannot support it', function (): void {
    it('throws when the consumer User model class does not exist', function (): void {
        expect(fn () => invokeUserModelGuard(
            'Lightitlabs\\Tests\\Fixtures\\Google2FAUserModel\\MissingUser',
            FakeTwoFactorAuthenticatable::class,
        ))
            ->toThrow(RuntimeException::class, 'Could not find');
    });

    it('throws when the consumer User model does not extend the two-factor authenticatable base class', function (): void {
        expect(fn () => invokeUserModelGuard(
            UserNotExtendingFakeTwoFactorAuthenticatable::class,
            FakeTwoFactorAuthenticatable::class,
        ))
            ->toThrow(RuntimeException::class, 'does not extend');
    });

    it('passes silently when the consumer User model extends the two-factor authenticatable base class', function (): void {
        expect(fn () => invokeUserModelGuard(
            UserExtendingFakeTwoFactorAuthenticatable::class,
            FakeTwoFactorAuthenticatable::class,
        ))
            ->not->toThrow(RuntimeException::class);
    });
});
