<?php

declare(strict_types=1);

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Event;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\FakePasskey;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\LoginByUserAction;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyCeremonyService;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyChallengeExpiredException;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyChallengeStore;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyLoginAction;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\PasskeyLoginDto;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\Trace;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\TwoFactorChallengeException;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\UnauthenticatedException;
use Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub\User;

$fixtureNamespace = 'Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub';

$fakes = <<<'PHP'
    <?php

    declare(strict_types=1);

    namespace Lightitlabs\Tests\Fixtures\PasskeyLoginActionStub;

    final class User
    {
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

    final class TwoFactorChallengeException extends \RuntimeException
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

    final class LoginByUserAction
    {
        public int $calls = 0;

        public function __construct(public bool $challenges)
        {
        }

        public function execute(User $user): void
        {
            Trace::record('loginByUser');
            ++$this->calls;

            if ($this->challenges) {
                throw new TwoFactorChallengeException('Two-factor authentication required.');
            }
        }

        public function executeAfterChallenge(User $user): void
        {
            throw new \LogicException('The sign-in must go through the 2FA gate.');
        }
    }
    PHP;

$stub = (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/Actions/PasskeyLoginAction.stub');
$action = preg_replace(
    '/^use Lightit\\\\[^;]*\\\\(\w+);$/m',
    'use ' . $fixtureNamespace . '\\\\$1;',
    str_replace('namespace Lightit\Authentication\Domain\Actions;', "namespace {$fixtureNamespace};", $stub),
);

foreach ([$fakes, (string) $action] as $index => $source) {
    $tempFile = sys_get_temp_dir() . "/passkey-login-action-stub-{$index}-" . bin2hex(random_bytes(6)) . '.php';
    file_put_contents($tempFile, $source);
    require_once $tempFile;
    unlink($tempFile);
}

describe('PasskeyLoginAction stub', function (): void {
    beforeEach(function (): void {
        $this->request = Request::create('/api/auth/passkeys/login', 'POST');
        $this->request->setLaravelSession(new Store('test', new ArraySessionHandler(1)));
        $this->user = new User();
        $this->passkey = new FakePasskey($this->user);
        $this->dto = new PasskeyLoginDto(str_repeat('a', 32), '{}');
    });

    $action = function (PasskeyChallengeStore $store, LoginByUserAction $login): PasskeyLoginAction {
        return new PasskeyLoginAction($this->request, new PasskeyCeremonyService($this->passkey), $store, $login);
    };

    it('signs the passkey owner in through LoginByUserAction::execute() exactly once', function () use ($action): void {
        $login = new LoginByUserAction(challenges: false);

        $user = $action->call($this, new PasskeyChallengeStore('{}'), $login)->execute($this->dto);

        expect($user)->toBe($this->user)
            ->and($login->calls)->toBe(1)
            ->and($this->passkey->saves)->toBe(1);
    });

    it(
        'lets the 2FA challenge through and still stores the new counter and last use',
        function () use ($action): void {
            $login = new LoginByUserAction(challenges: true);

            expect(fn () => $action->call($this, new PasskeyChallengeStore('{}'), $login)->execute($this->dto))
                ->toThrow(TwoFactorChallengeException::class);

            expect($login->calls)->toBe(1)
                ->and($this->passkey->saves)->toBe(1)
                ->and($this->passkey->sign_count)->toBe(9)
                ->and($this->passkey->backup_status)->toBeTrue()
                ->and($this->passkey->last_used_at)->not->toBeNull();
        }
    );

    it(
        'checks and saves the counter in one transaction, outside of which it spends the challenge and runs the 2FA gate',
        function () use ($action): void {
            Trace::$steps = [];
            Event::listen(TransactionCommitted::class, static function (): void {
                Trace::$steps[] = 'commit';
            });

            $action->call($this, new PasskeyChallengeStore('{}'), new LoginByUserAction(challenges: false))
                ->execute($this->dto);

            expect(Trace::$steps)
                ->toBe(['pullLogin@0', 'verifyAssertion@1', 'saveOrFail@1', 'commit', 'loginByUser@0']);
        }
    );

    it('answers an expired or spent challenge without verifying or signing anyone in', function () use ($action): void {
        $login = new LoginByUserAction(challenges: false);

        expect(fn () => $action->call($this, new PasskeyChallengeStore(null), $login)->execute($this->dto))
            ->toThrow(PasskeyChallengeExpiredException::class);

        expect($login->calls)->toBe(0)
            ->and($this->passkey->saves)->toBe(0);
    });

    it('refuses a request without a session before spending the challenge', function () use ($action): void {
        $this->request = Request::create('/api/auth/passkeys/login', 'POST');
        $store = new PasskeyChallengeStore('{}');

        expect(fn () => $action->call($this, $store, new LoginByUserAction(challenges: false))->execute($this->dto))
            ->toThrow(UnauthenticatedException::class);

        expect($store->pulled)->toBe([]);
    });

    it('never logs the user in itself', function (): void {
        expect((string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/Actions/PasskeyLoginAction.stub'))
            ->not->toMatch('/->login\(/')
            ->not->toContain('executeAfterChallenge');
    });
});
