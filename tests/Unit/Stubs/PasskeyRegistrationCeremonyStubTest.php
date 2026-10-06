<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\FakeAuthenticator;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\Passkey;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyCeremonyService;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyChallengeStore;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\PasskeyRegistrationFailedException;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StartPasskeyRegistrationAction;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StorePasskeyAction;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StorePasskeyDto;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\StubLoader;
use Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub\User;
use ParagonIE\ConstantTime\Base64UrlSafe;

StubLoader::load(
    'Models/Passkey.stub',
    'DataTransferObjects/VerifiedPasskeyDto.stub',
    'DataTransferObjects/StorePasskeyDto.stub',
    'Exceptions/PasskeyRegistrationFailedException.stub',
    'Exceptions/PasskeyChallengeExpiredException.stub',
    'Exceptions/PasskeyAlreadyRegisteredException.stub',
    'Services/PasskeyCeremonyService.stub',
    'PasskeyChallengeStore.stub',
    'Actions/StartPasskeyRegistrationAction.stub',
    'Actions/StorePasskeyAction.stub',
);

const PASSKEY_TEST_ORIGIN = 'https://app.example.test';

function rejectedRegistration(callable $attempt): PasskeyRegistrationFailedException
{
    try {
        $attempt();
    } catch (PasskeyRegistrationFailedException $exception) {
        return $exception;
    }

    test()->fail('Expected a PasskeyRegistrationFailedException to be thrown.');
}

describe('PasskeyCeremonyService::verifyRegistration() stub', function (): void {
    beforeEach(function (): void {
        Config::set('passkeys.relying_party', ['id' => 'example.test', 'name' => 'Example App']);
        Config::set('passkeys.allowed_origins', [PASSKEY_TEST_ORIGIN]);
        Config::set('passkeys.user_handle_secret', 'test-user-handle-secret');
        Config::set('passkeys.challenge_ttl_seconds', 300);

        User::createTable();
        StubLoader::migratePasskeysTable();

        $this->user = User::make('jane.doe@example.test', 'Jane Doe');
        $this->exceptionHandler = Mockery::spy(ExceptionHandler::class);
        $this->service = new PasskeyCeremonyService($this->exceptionHandler);
        $this->authenticator = new FakeAuthenticator();
        $this->creationOptions = $this->service->creationOptions($this->user);
        $this->decodedCreationOptions = json_decode($this->creationOptions, true, flags: \JSON_THROW_ON_ERROR);
    });

    it(
        'accepts a ceremony signed for this challenge, origin and relying party with user verification',
        function (): void {
            $verified = $this->service->verifyRegistration(
                $this->creationOptions,
                $this->authenticator->attestation($this->decodedCreationOptions, PASSKEY_TEST_ORIGIN),
            );
    
            expect($verified->credentialId)->toBe(Base64UrlSafe::encodeUnpadded($this->authenticator->credentialId))
                ->and($verified->credentialIdHash)->toBe(hash('sha256', $this->authenticator->credentialId))
                ->and($verified->signCount)->toBe(0)
                ->and($verified->transports)->toBe(['internal']);
        }
    );

    it(
        'rejects a ceremony the WebAuthn checks fail with 422 passkey_registration_failed',
        function (callable $credential): void {
            $exception = rejectedRegistration(fn () => $this->service->verifyRegistration(
                $this->creationOptions,
                $credential($this->authenticator, $this->decodedCreationOptions),
            ));
    
            expect($exception->statusCode())->toBe(422)
                ->and($exception->errorCode())->toBe('passkey_registration_failed');
            $this->exceptionHandler->shouldNotHaveReceived('report');
        }
    )->with([
        'an origin outside allowed_origins' => [
            static fn (FakeAuthenticator $authenticator, array $options): string => $authenticator
                ->attestation($options, 'https://evil.example.test'),
        ],
        'a challenge other than the one issued' => [
            static fn (FakeAuthenticator $authenticator, array $options): string => $authenticator
                ->attestation(
                    $options,
                    PASSKEY_TEST_ORIGIN,
                    challenge: Base64UrlSafe::encodeUnpadded(random_bytes(32))
                ),
        ],
        'a credential scoped to another relying party' => [
            static fn (FakeAuthenticator $authenticator, array $options): string => $authenticator
                ->attestation($options, PASSKEY_TEST_ORIGIN, relyingPartyId: 'other.test'),
        ],
        'an authenticator that skipped user verification' => [
            static fn (FakeAuthenticator $authenticator, array $options): string => $authenticator
                ->attestation($options, PASSKEY_TEST_ORIGIN, userVerified: false),
        ],
    ]);

    it('logs a rejected ceremony as a warning with the reason and no credential data', function (): void {
        Log::spy();

        rejectedRegistration(fn () => $this->service->verifyRegistration(
            $this->creationOptions,
            $this->authenticator->attestation($this->decodedCreationOptions, 'https://evil.example.test'),
        ));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'passkey registration rejected'
                && array_keys($context) === ['reason', 'message']
                && is_a($context['reason'], Throwable::class, true)
                && is_string($context['message']),
        );
        $this->exceptionHandler->shouldNotHaveReceived('report');
    });

    it(
        'rejects a body that is not a credential with 422 passkey_registration_failed',
        function (string $credential): void {
            $exception = rejectedRegistration(
                fn () => $this->service->verifyRegistration($this->creationOptions, $credential)
            );
    
            expect($exception->statusCode())->toBe(422)
                ->and($exception->errorCode())->toBe('passkey_registration_failed');
        }
    )->with([
        'malformed JSON' => ['{'],
        'a JSON object with no credential fields' => ['{"id":"not-a-credential"}'],
    ]);

    it('stores the passkey for an accepted ceremony and nothing for a rejected one', function (): void {
        $challengeStore = new PasskeyChallengeStore();
        $start = new StartPasskeyRegistrationAction($this->service, $challengeStore);
        $store = new StorePasskeyAction($this->service, $challengeStore);

        $rejectedOptions = json_decode($start->execute($this->user), true, flags: \JSON_THROW_ON_ERROR);
        rejectedRegistration(fn () => $store->execute($this->user, new StorePasskeyDto(
            name: 'Laptop',
            credential: $this->authenticator->attestation($rejectedOptions, 'https://evil.example.test'),
        )));

        expect(Passkey::query()->count())->toBe(0);

        $acceptedOptions = json_decode($start->execute($this->user), true, flags: \JSON_THROW_ON_ERROR);
        $passkey = $store->execute($this->user, new StorePasskeyDto(
            name: 'Laptop',
            credential: $this->authenticator->attestation($acceptedOptions, PASSKEY_TEST_ORIGIN),
        ));

        expect(Passkey::query()->sole()->is($passkey))->toBeTrue()
            ->and($passkey->user_id)->toBe($this->user->id)
            ->and($passkey->name)->toBe('Laptop');
    });
});

describe('PasskeyCeremonyService stub without its relying party configured', function (): void {
    beforeEach(function (): void {
        Config::set('passkeys.relying_party', ['id' => 'example.test', 'name' => 'Example App']);
        Config::set('passkeys.allowed_origins', [PASSKEY_TEST_ORIGIN]);
        Config::set('passkeys.user_handle_secret', 'test-user-handle-secret');
        Config::set('passkeys.challenge_ttl_seconds', 300);

        User::createTable();
        StubLoader::migratePasskeysTable();

        $this->user = User::make('jane.doe@example.test', 'Jane Doe');
        $this->service = new PasskeyCeremonyService(Mockery::spy(ExceptionHandler::class));
    });

    it('refuses to issue options without PASSKEYS_RP_ID', function (?string $relyingPartyId): void {
        Config::set('passkeys.relying_party.id', $relyingPartyId);

        expect(fn () => $this->service->creationOptions($this->user))
            ->toThrow(RuntimeException::class, 'Set PASSKEYS_RP_ID');
    })->with(['unset' => [null], 'empty' => ['']]);

    it('refuses to verify a ceremony without PASSKEYS_ALLOWED_ORIGINS', function (): void {
        $options = $this->service->creationOptions($this->user);
        Config::set('passkeys.allowed_origins', []);

        expect(fn () => $this->service->verifyRegistration(
            $options,
            new FakeAuthenticator()->attestation(
                json_decode($options, true, flags: \JSON_THROW_ON_ERROR),
                PASSKEY_TEST_ORIGIN,
            ),
        ))->toThrow(RuntimeException::class, 'Set PASSKEYS_ALLOWED_ORIGINS');
    });

    it('refuses to issue options without PASSKEYS_USER_HANDLE_SECRET', function (?string $secret): void {
        Config::set('passkeys.user_handle_secret', $secret);

        expect(fn () => $this->service->creationOptions($this->user))
            ->toThrow(RuntimeException::class, 'Set PASSKEYS_USER_HANDLE_SECRET');
    })->with(['unset' => [null], 'empty' => ['']]);
});
