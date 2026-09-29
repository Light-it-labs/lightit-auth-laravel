<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Illuminate\Session\Store as SessionStore;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub\FakeUser;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub\IssueTwoFactorChallengeAction;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub\PlainUser;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub\TwoFactorChallengeException;

/**
 * IssueTwoFactorChallengeAction.stub is a template for the consuming app - it hardcodes
 * `Lightit\Users\Domain\Models\User` and lives under `Lightit\` namespaces
 * this package never loads directly. Rendered here into a private test
 * namespace (FakeUser stands in for the real User model) so its branching
 * logic is exercised without a full consumer app.
 */
function renderIssueTwoFactorChallengeActionStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/' . $relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain\Actions;',
            'namespace Lightit\Authentication\Domain\Enums;',
            'namespace Lightit\Authentication\Domain\Exceptions;',
            "use Lightit\Authentication\Domain\Enums\TwoFactorReason;\n",
            "use Lightit\Authentication\Domain\Exceptions\TwoFactorChallengeException;\n",
            'use Lightit\Authentication\Domain\TwoFactorAuthenticatable;',
            'use Lightit\Users\Domain\Models\User;',
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub;',
            'namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub;',
            'namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub;',
            '',
            '',
            // FakeUser stands in for TwoFactorAuthenticatable, so a FakeUser passes the
            // instanceof guard below and exercises the 2FA branching.
            'use Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub\FakeUser as TwoFactorAuthenticatable;',
            // User is aliased to the plain Authenticatable contract instead of FakeUser, so
            // PlainUser (Authenticatable but not TwoFactorAuthenticatable) can also satisfy
            // execute()'s type-hint and exercise the fail-closed LogicException below.
            'use Illuminate\Contracts\Auth\Authenticatable as User;',
        ],
        $contents,
    );
}

function requireRenderedChallengeActionStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir() . '/issue-two-factor-challenge-action-stub-' . md5($relativePath) . '.php';
    file_put_contents($tempFile, renderIssueTwoFactorChallengeActionStub($relativePath));
    require_once $tempFile;
}

requireRenderedChallengeActionStub('Shared/Auth/Enums/TwoFactorReason.stub');
requireRenderedChallengeActionStub('Shared/Auth/Exceptions/TwoFactorChallengeException.stub');
requireRenderedChallengeActionStub('Shared/Auth/Actions/IssueTwoFactorChallengeAction.stub');

/**
 * Builds a challenge action backed by a real `SessionGuard` over an array session store,
 * standing in for the authenticated session the host login already created
 * by the time `execute()` runs (see the challenge action's own docblock).
 *
 * @return array{0: IssueTwoFactorChallengeAction, 1: Guard, 2: SessionStore}
 */
function makeIssueTwoFactorChallengeAction(): array
{
    config([
        'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
        'auth.providers.users' => ['driver' => 'eloquent', 'model' => FakeUser::class],
        'session.driver' => 'array',
    ]);

    /** @var SessionStore $session */
    $session = app('session.store');
    $session->start();

    $request = Request::create('/login');
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    /** @var AuthFactory $authFactory */
    $authFactory = app(AuthFactory::class);
    $guard = $authFactory->guard('web');

    return [new IssueTwoFactorChallengeAction($authFactory, $request), $guard, $session];
}

it('never reports a 2FA challenge to the app\'s exception handler', function (): void {
    expect(is_a(TwoFactorChallengeException::class, ShouldntReport::class, true))->toBeTrue();
});

describe('IssueTwoFactorChallengeAction stub', function (): void {
    beforeEach(function (): void {
        config(['google2fa.challenge_ttl_minutes' => 15]);
    });

    it('does nothing when 2FA is disabled', function (): void {
        config(['google2fa.enabled' => false]);

        [$action] = makeIssueTwoFactorChallengeAction();

        $action->execute(new FakeUser(hasSecretStored: true, hasConfigured: true));
    })->throwsNoExceptions();

    it(
        'does nothing when google2fa.enabled is entirely absent, as in an OTP-only or Google-SSO-only install',
        function (): void {
            config(['google2fa' => null]);
    
            [$action] = makeIssueTwoFactorChallengeAction();
    
            $action->execute(new FakeUser(hasSecretStored: true, hasConfigured: true));
        }
    )->throwsNoExceptions();

    it('does nothing for a plain user that cannot be challenged when 2FA is disabled', function (): void {
        config(['google2fa.enabled' => false]);

        [$action] = makeIssueTwoFactorChallengeAction();

        $action->execute(new PlainUser());
    })->throwsNoExceptions();

    it(
        'fails closed with a LogicException when 2FA is enabled but the user is not a TwoFactorAuthenticatable',
        function (): void {
            config(['google2fa.enabled' => true]);
    
            [$action] = makeIssueTwoFactorChallengeAction();
    
            $action->execute(new PlainUser());
        }
    )->throws(LogicException::class);

    it('throws a setup-required challenge when 2FA is mandatory and the user has no secret', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => true]);

        [$action] = makeIssueTwoFactorChallengeAction();

        try {
            $action->execute(new FakeUser(hasSecretStored: false, hasConfigured: false));
            test()->fail('Expected a TwoFactorChallengeException to be thrown.');
        } catch (TwoFactorChallengeException $exception) {
            expect($exception->tokenType)->toBe('setup_required');
            expect($exception->expiresIn)->toBe(15 * 60);
        }
    });

    it('throws a verification-required challenge when the user already has a secret stored', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);

        [$action] = makeIssueTwoFactorChallengeAction();

        try {
            $action->execute(new FakeUser(hasSecretStored: true, hasConfigured: true));
            test()->fail('Expected a TwoFactorChallengeException to be thrown.');
        } catch (TwoFactorChallengeException $exception) {
            expect($exception->tokenType)->toBe('verification_required');
        }
    });

    it('does nothing when 2FA is optional and the user has not configured it', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);

        [$action] = makeIssueTwoFactorChallengeAction();

        $action->execute(new FakeUser(hasSecretStored: false, hasConfigured: false));
    })->throwsNoExceptions();

    it(
        'never hands out a verification-required token for a secret-stored-but-unconfigured (abandoned enrolment) user',
        function (): void {
            config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);
    
            [$action] = makeIssueTwoFactorChallengeAction();
    
            // Regression guard: an attacker who only knows the password must not receive
            // verification_required for a user who stored a secret but never activated it -
            // that token would let them call /2fa/setup and take over the account's 2FA.
            $action->execute(new FakeUser(hasSecretStored: true, hasConfigured: false));
        }
    )->throwsNoExceptions();

    it(
        'still issues a setup-required challenge for a secret-stored-but-unconfigured user when 2FA is mandatory, so enrolment can finish',
        function (): void {
            config(['google2fa.enabled' => true, 'google2fa.mandatory' => true]);
    
            [$action] = makeIssueTwoFactorChallengeAction();
    
            try {
                $action->execute(new FakeUser(hasSecretStored: true, hasConfigured: false));
                test()->fail('Expected a TwoFactorChallengeException to be thrown.');
            } catch (TwoFactorChallengeException $exception) {
                expect($exception->tokenType)->toBe('setup_required');
            }
        }
    );

    it(
        'falls back to safe defaults when a vendor-shaped config is missing mandatory and challenge_ttl_minutes',
        function (): void {
            config(['google2fa' => ['enabled' => true]]);
    
            [$action] = makeIssueTwoFactorChallengeAction();
    
            try {
                $action->execute(new FakeUser(hasSecretStored: false, hasConfigured: false));
                test()->fail('Expected a TwoFactorChallengeException to be thrown.');
            } catch (TwoFactorChallengeException $exception) {
                expect($exception->tokenType)->toBe('setup_required');
                expect($exception->expiresIn)->toBe(15 * 60);
            }
        }
    );

    it('tears the host login session down before throwing a challenge', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);

        [$action, $guard, $session] = makeIssueTwoFactorChallengeAction();

        $user = new FakeUser(hasSecretStored: true, hasConfigured: true);
        $guard->login($user);
        expect($guard->check())->toBeTrue();

        $sessionIdBeforeChallenge = $session->getId();

        try {
            $action->execute($user);
            test()->fail('Expected a TwoFactorChallengeException to be thrown.');
        } catch (TwoFactorChallengeException) {
            // The challenge itself is asserted by the other tests above -
            // this test only cares about the session it leaves behind.
        }

        expect($guard->check())->toBeFalse();
        expect($session->getId())->not->toBe($sessionIdBeforeChallenge);
    });
});

it(
    'guards every TwoFactorAuthenticatable-only call with an instanceof check that fails closed, so a plain User only ever stays type-safe when 2FA is disabled',
    function (): void {
        $stub = (string) file_get_contents(
            __DIR__ . '/../../../src/Stubs/Shared/Auth/Actions/IssueTwoFactorChallengeAction.stub'
        );
    
        expect($stub)->toContain('if (! $user instanceof TwoFactorAuthenticatable) {')
            ->and($stub)->toContain('throw new LogicException(');
    }
);
