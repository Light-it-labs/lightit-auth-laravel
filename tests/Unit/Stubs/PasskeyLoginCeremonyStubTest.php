<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\FakeAuthenticator;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\LockRecordingGrammar;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyCeremonyService;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyChallengeStore;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyLoginFailedException;
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
        $this->service = new PasskeyCeremonyService(Mockery::spy(ExceptionHandler::class));
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

    it('rejects an assertion whose counter does not move past the stored one', function (): void {
        $this->passkey->sign_count = 5;
        $this->passkey->saveOrFail();

        $exception = rejectedLogin(
            fn () => $this->service->verifyAssertion($this->requestOptions, ($this->signedAssertion)(5))
        );

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('passkey_login_failed');
    });
});
