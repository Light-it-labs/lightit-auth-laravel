<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\FakeAuthenticator;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\LockRecordingGrammar;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyCeremonyService;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyChallengeStore;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyLoginFailedException;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyLoginRequest;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StartPasskeyRegistrationAction;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StorePasskeyAction;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StorePasskeyDto;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StubLoader;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\User;

StubLoader::load(
    'Models/Passkey.stub',
    'DataTransferObjects/VerifiedPasskeyDto.stub',
    'DataTransferObjects/VerifiedPasskeyAssertionDto.stub',
    'DataTransferObjects/StorePasskeyDto.stub',
    'DataTransferObjects/PasskeyLoginDto.stub',
    'Exceptions/PasskeyRegistrationFailedException.stub',
    'Exceptions/PasskeyChallengeExpiredException.stub',
    'Exceptions/PasskeyAlreadyRegisteredException.stub',
    'Exceptions/PasskeyLoginFailedException.stub',
    'Exceptions/PasskeyNotRecognisedException.stub',
    'Services/PasskeyCeremonyService.stub',
    'PasskeyChallengeStore.stub',
    'Actions/StartPasskeyRegistrationAction.stub',
    'Actions/StorePasskeyAction.stub',
    'Requests/PasskeyLoginRequest.stub',
);

const PASSKEY_LOGIN_TEST_ORIGIN = 'https://app.example.test';

function rejectedLogin(callable $attempt): PasskeyLoginFailedException
{
    try {
        $attempt();
    } catch (PasskeyLoginFailedException $exception) {
        return $exception;
    }

    test()->fail('Expected a PasskeyLoginFailedException to be thrown.');
}

describe('PasskeyCeremonyService::verifyAssertion() stub', function (): void {
    beforeEach(function (): void {
        Config::set('passkeys.relying_party', ['id' => 'example.test', 'name' => 'Example App']);
        Config::set('passkeys.allowed_origins', [PASSKEY_LOGIN_TEST_ORIGIN]);
        Config::set('passkeys.user_handle_secret', 'test-user-handle-secret');
        Config::set('passkeys.challenge_ttl_seconds', 300);

        User::createTable();
        StubLoader::migratePasskeysTable();

        $this->user = User::make('jane.doe@example.test', 'Jane Doe');
        $this->exceptionHandler = Mockery::spy(ExceptionHandler::class);
        $this->service = new PasskeyCeremonyService($this->exceptionHandler);
        $this->authenticator = new FakeAuthenticator();

        $challengeStore = new PasskeyChallengeStore();
        $creationOptions = (new StartPasskeyRegistrationAction($this->service, $challengeStore))->execute($this->user);
        $this->passkey = (new StorePasskeyAction(
            $this->service,
            $challengeStore
        ))->execute(
            $this->user,
            new StorePasskeyDto(
                name: 'Laptop',
                credential: $this->authenticator->attestation(
                    json_decode($creationOptions, true, flags: \JSON_THROW_ON_ERROR),
                    PASSKEY_LOGIN_TEST_ORIGIN,
                ),
            )
        );

        $this->requestOptions = $this->service->requestOptions();
        $this->userHandle = hash_hmac('sha256', 'passkey-user:' . $this->user->id, 'test-user-handle-secret', true);
        $this->signedAssertion = fn (int $counter): string => $this->authenticator->assertion(
            json_decode($this->requestOptions, true, flags: \JSON_THROW_ON_ERROR),
            PASSKEY_LOGIN_TEST_ORIGIN,
            $this->userHandle,
            $counter,
        );
    });

    it('accepts the owner\'s signed assertion and reads the credential row under a row lock', function (): void {
        $grammar = new LockRecordingGrammar(DB::connection());
        DB::connection()->setQueryGrammar($grammar);

        $verified = DB::transaction(
            fn () => $this->service->verifyAssertion($this->requestOptions, ($this->signedAssertion)(1))
        );

        expect($verified->passkey->is($this->passkey))->toBeTrue()
            ->and($verified->signCount)->toBe(1)
            ->and($grammar->lockedForUpdate)->toBe(['passkeys']);
    });

    it(
        'asks for user verification and rejects an assertion signed without it, so a passkey counts as multi-factor',
        function (): void {
            $unverified = $this->authenticator->assertion(
                json_decode($this->requestOptions, true, flags: \JSON_THROW_ON_ERROR),
                PASSKEY_LOGIN_TEST_ORIGIN,
                $this->userHandle,
                1,
                userVerified: false,
            );

            $exception = rejectedLogin(fn () => $this->service->verifyAssertion($this->requestOptions, $unverified));

            expect(json_decode($this->requestOptions, true, flags: \JSON_THROW_ON_ERROR)['userVerification'])
                ->toBe('required')
                ->and($exception->statusCode())->toBe(422)
                ->and($exception->errorCode())->toBe('passkey_login_failed')
                ->and($this->passkey->refresh()->sign_count)->toBe(0);
        }
    );

    it('rejects an assertion whose counter does not move past the stored one', function (): void {
        $this->passkey->sign_count = 5;
        $this->passkey->saveOrFail();

        $exception = rejectedLogin(
            fn () => $this->service->verifyAssertion($this->requestOptions, ($this->signedAssertion)(5))
        );

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('passkey_login_failed');
    });

    it('logs a rejected assertion as a warning with the reason and no credential data', function (): void {
        $this->passkey->sign_count = 5;
        $this->passkey->saveOrFail();
        Log::spy();

        rejectedLogin(fn () => $this->service->verifyAssertion($this->requestOptions, ($this->signedAssertion)(5)));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'passkey sign-in rejected'
                && array_keys($context) === ['reason', 'message']
                && is_a($context['reason'], Throwable::class, true)
                && is_string($context['message']),
        );
        $this->exceptionHandler->shouldNotHaveReceived('report');
    });

    it('turns a credential with malformed UTF-8 into the 422 ceremony rejection, not a 500', function (): void {
        $credential = json_decode(($this->signedAssertion)(1), true, flags: \JSON_THROW_ON_ERROR);
        $credential['response']['clientDataJSON'] .= "\xB1";

        $dto = PasskeyLoginRequest::create('/api/auth/passkeys/login', 'POST', [
            PasskeyLoginRequest::CEREMONY_ID => str_repeat('a', 32),
            PasskeyLoginRequest::CREDENTIAL => $credential,
        ])->toDto();

        $exception = rejectedLogin(fn () => $this->service->verifyAssertion($this->requestOptions, $dto->credential));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('passkey_login_failed');
    });
});
