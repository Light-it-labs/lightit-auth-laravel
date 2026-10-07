<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Request;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Event;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Auth\Installers\SharedLoginFiles;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\FakePasskey;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\IssueTwoFactorChallengeAction;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\LoginByUserAction;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyCeremonyService;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyChallengeExpiredException;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyChallengeStore;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyLoginAction;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyLoginDto;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\Trace;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\UnauthenticatedException;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\User;

$fixtureNamespace = 'Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub';

$fakes = <<<'PHP'
    <?php

    declare(strict_types=1);

    namespace Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub;

    abstract class TwoFactorAuthenticatable implements \Illuminate\Contracts\Auth\Authenticatable
    {
        abstract public function hasTwoFactorAuthenticationConfigured(): bool;

        public function create2faToken(int $ttlInMinutes, TwoFactorReason $reason): string
        {
            Trace::record('challenge');

            return 'challenge-token';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string|null
        {
            return null;
        }

        public function setRememberToken($value): void
        {
        }

        public function getRememberTokenName(): string
        {
            return '';
        }
    }

    final class User extends TwoFactorAuthenticatable
    {
        public function __construct(private readonly bool $twoFactorConfigured = false)
        {
        }

        public function hasTwoFactorAuthenticationConfigured(): bool
        {
            return $this->twoFactorConfigured;
        }
    }

    final class Trace
    {
        /** @var list<string> */
        public static array $steps = [];

        public static function record(string $step): void
        {
            self::$steps[] = $step . '@' . \Illuminate\Support\Facades\DB::transactionLevel();
        }
    }

    final class PasskeyLoginDto
    {
        public function __construct(public string $ceremonyId, public string $credential)
        {
        }
    }

    final class PasskeyChallengeExpiredException extends \RuntimeException
    {
    }

    final class PasskeyLoginFailedException extends \RuntimeException
    {
    }

    final class PasskeyNotRecognisedException extends \RuntimeException
    {
    }

    final class UnauthenticatedException extends \RuntimeException
    {
    }

    final class FakePasskey
    {
        public int $sign_count = 1;

        public bool $backup_eligible = false;

        public bool $backup_status = false;

        public mixed $last_used_at = null;

        public int $saves = 0;

        public function __construct(public User $user)
        {
        }

        public function saveOrFail(): bool
        {
            Trace::record('saveOrFail');
            ++$this->saves;

            return true;
        }
    }

    final class VerifiedPasskeyAssertionDto
    {
        public function __construct(
            public FakePasskey $passkey,
            public int $signCount,
            public bool $backupEligible,
            public bool $backupStatus,
        ) {
        }
    }

    final class PasskeyChallengeStore
    {
        /** @var list<string> */
        public array $pulled = [];

        public function __construct(public string|null $options)
        {
        }

        public function pullLogin(string $ceremonyId): string|null
        {
            Trace::record('pullLogin');
            $this->pulled[] = $ceremonyId;
            $options = $this->options;
            $this->options = null;

            return $options;
        }
    }

    final class PasskeyCeremonyService
    {
        public function __construct(public FakePasskey $passkey)
        {
        }

        public function verifyAssertion(string $options, string $credential): VerifiedPasskeyAssertionDto
        {
            Trace::record('verifyAssertion');

            return new VerifiedPasskeyAssertionDto($this->passkey, 9, true, true);
        }
    }
    PHP;

$render = static fn (string $stub): string => (string) preg_replace(
    ['/^namespace Lightit\\\\[\w\\\\]+;$/m', '/^use Lightit\\\\[^;]*\\\\(\w+);$/m'],
    ["namespace {$fixtureNamespace};", 'use ' . $fixtureNamespace . '\\\\$1;'],
    $stub,
);

$sources = [
    $fakes,
    ...array_map(
        static fn (string $stub): string => $render((string) file_get_contents(SharedLoginFiles::stubsPath() . $stub)),
        [
            '/Enums/TwoFactorReason.stub',
            '/Exceptions/TwoFactorChallengeException.stub',
            '/Actions/IssueTwoFactorChallengeAction.stub',
            '/Actions/LoginByUserAction.stub',
        ],
    ),
    $render((string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/Actions/PasskeyLoginAction.stub')),
];

foreach ($sources as $index => $source) {
    $tempFile = sys_get_temp_dir() . "/passkey-login-action-stub-{$index}-" . bin2hex(random_bytes(6)) . '.php';
    file_put_contents($tempFile, $source);
    require_once $tempFile;
    unlink($tempFile);
}

describe('PasskeyLoginAction stub, with the real shared LoginByUserAction', function (): void {
    beforeEach(function (): void {
        config([
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
            'auth.providers.users' => ['driver' => 'eloquent', 'model' => User::class],
            'session.driver' => 'array',
            'google2fa' => null,
        ]);

        /** @var SessionStore $session */
        $session = app('session.store');
        $session->start();
        $this->session = $session;

        $this->request = Request::create('/api/auth/passkeys/login', 'POST');
        $this->request->setLaravelSession($session);
        app()->instance('request', $this->request);

        /** @var AuthFactory $auth */
        $auth = app(AuthFactory::class);
        $this->auth = $auth;

        /** @var Guard $guard */
        $guard = $auth->guard('web');
        $this->guard = $guard;

        $this->dto = new PasskeyLoginDto(str_repeat('a', 32), '{}');
        Trace::$steps = [];
    });

    $action = function (User $user, PasskeyChallengeStore $store): PasskeyLoginAction {
        $this->passkey = new FakePasskey($user);

        return new PasskeyLoginAction(
            $this->request,
            new PasskeyCeremonyService($this->passkey),
            $store,
            new LoginByUserAction(
                $this->auth,
                $this->request,
                new IssueTwoFactorChallengeAction($this->auth, $this->request),
            ),
        );
    };

    it(
        'signs the user straight in with a session, a regenerated id and the new counter',
        function (array $google2fa, bool $twoFactorConfigured) use ($action): void {
            config(['google2fa' => $google2fa]);
            $user = new User($twoFactorConfigured);
            $sessionIdBefore = $this->session->getId();

            $signedIn = $action->call($this, $user, new PasskeyChallengeStore('{}'))->execute($this->dto);

            expect($signedIn)->toBe($user)
                ->and($this->guard->user())->toBe($user)
                ->and($this->session->getId())->not->toBe($sessionIdBefore)
                ->and(Trace::$steps)->not->toContain('challenge@0')
                ->and($this->passkey->saves)->toBe(1)
                ->and($this->passkey->sign_count)->toBe(9)
                ->and($this->passkey->backup_status)->toBeTrue()
                ->and($this->passkey->last_used_at)->not->toBeNull();
        }
    )->with([
        'without 2FA' => [['enabled' => false], false],
        'a 2FA user: no challenge, the passkey is already multi-factor' => [
            ['enabled' => true, 'mandatory' => false, 'challenge_ttl_minutes' => 15],
            true,
        ],
        'mandatory 2FA and a user without TOTP: the passkey satisfies it' => [
            ['enabled' => true, 'mandatory' => true, 'challenge_ttl_minutes' => 15],
            false,
        ],
    ]);

    it(
        'checks and saves the counter in one transaction, outside of which it spends the challenge and logs in',
        function () use ($action): void {
            config(['google2fa' => ['enabled' => true, 'mandatory' => true]]);
            Event::listen(TransactionCommitted::class, static function (): void {
                Trace::$steps[] = 'commit';
            });
            Event::listen(Login::class, static function (): void {
                Trace::record('login');
            });

            $action->call($this, new User(twoFactorConfigured: true), new PasskeyChallengeStore('{}'))
                ->execute($this->dto);

            expect(Trace::$steps)
                ->toBe(['pullLogin@0', 'verifyAssertion@1', 'saveOrFail@1', 'commit', 'login@0']);
        }
    );

    it('answers an expired or spent challenge without verifying or signing anyone in', function () use ($action): void {
        expect(fn () => $action->call($this, new User(), new PasskeyChallengeStore(null))->execute($this->dto))
            ->toThrow(PasskeyChallengeExpiredException::class);

        expect($this->guard->check())->toBeFalse()
            ->and($this->passkey->saves)->toBe(0);
    });

    it('refuses a request without a session before spending the challenge', function () use ($action): void {
        $this->request = Request::create('/api/auth/passkeys/login', 'POST');
        $store = new PasskeyChallengeStore('{}');

        expect(fn () => $action->call($this, new User(), $store)->execute($this->dto))
            ->toThrow(UnauthenticatedException::class);

        expect($store->pulled)->toBe([])
            ->and($this->guard->check())->toBeFalse();
    });

    it('never logs the user in itself', function (): void {
        expect((string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/Actions/PasskeyLoginAction.stub'))
            ->not->toMatch('/->login\(/');
    });
});
