<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lightitlabs\Auth\Installers\SocialLoginInstaller;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\GoogleProvider;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\GoogleSigningKeys;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\GoogleTokens;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\InvalidSocialTokenException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\LoginByUserAction;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialAccount;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialEmailNotVerifiedException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialLoginAction;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialLoginDto;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialProviderRegistry;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialProviderUnavailableException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\SocialProviderUnknownException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\StubLoader;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\TwoFactorChallengeException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\UnauthenticatedException;
use Lightitlabs\Tests\Fixtures\SocialLoginStub\User;

StubLoader::load(
    'DataTransferObjects/SocialIdentityDto.stub',
    'DataTransferObjects/SocialLoginDto.stub',
    'Contracts/SocialProvider.stub',
    'Exceptions/InvalidSocialTokenException.stub',
    'Exceptions/SocialEmailNotVerifiedException.stub',
    'Exceptions/SocialProviderUnknownException.stub',
    'Exceptions/SocialProviderUnavailableException.stub',
    'Models/SocialAccount.stub',
    'SocialProviders/GoogleSigningKeys.stub',
    'SocialProviders/GoogleProvider.stub',
    'SocialProviderRegistry.stub',
    'Actions/SocialLoginAction.stub',
);

/**
 * @template T of Throwable
 *
 * @param class-string<T> $class
 *
 * @return T
 */
function socialLoginFailure(string $class, callable $attempt): Throwable
{
    try {
        $attempt();
    } catch (Throwable $exception) {
        expect($exception)->toBeInstanceOf($class);

        return $exception;
    }

    test()->fail("Expected {$class} to be thrown.");
}

beforeEach(function (): void {
    Config::set('social-login.providers', ['google' => GoogleProvider::class]);
    Config::set('social-login.google.client_id', GoogleTokens::CLIENT_ID);

    $this->tokens = new GoogleTokens();
    $this->http = new Factory();
    $this->http->preventStrayRequests();
    $this->http->fake([
        GoogleSigningKeys::JWKS_URL => Factory::response(
            $this->tokens->jwks(),
            200,
            ['Cache-Control' => 'public, max-age=120, must-revalidate']
        ),
    ]);
    $this->cache = new Repository(new ArrayStore());
    $this->app->instance(GoogleSigningKeys::class, new GoogleSigningKeys($this->http, $this->cache));
});

describe('GoogleProvider stub', function (): void {
    beforeEach(function (): void {
        $this->provider = $this->app->make(GoogleProvider::class);
    });

    it('returns the verified Google identity of a token issued for this app', function (): void {
        $identity = $this->provider->verify($this->tokens->sign());

        expect($identity->provider)->toBe('google')
            ->and($identity->providerUserId)->toBe('100000000000000000001')
            ->and($identity->email)->toBe('demo@example.com')
            ->and($identity->emailVerified)->toBeTrue()
            ->and($identity->name)->toBe('Demo User');
    });

    it('accepts the scheme-less issuer Google also signs with', function (): void {
        expect($this->provider->verify($this->tokens->sign(['iss' => 'accounts.google.com']))->providerUserId)
            ->toBe('100000000000000000001');
    });

    it(
        'tolerates a server clock slightly behind Google\'s and leaves php-jwt\'s global leeway as it was',
        function (): void {
            $before = JWT::$leeway;
    
            $identity = $this->provider->verify($this->tokens->sign(['iat' => time() + 5, 'nbf' => time() + 5]));
    
            socialLoginFailure(
                InvalidSocialTokenException::class,
                fn () => $this->provider->verify($this->tokens->sign(['nbf' => time() + 3600])),
            );
    
            expect($identity->providerUserId)->toBe('100000000000000000001')
                ->and(JWT::$leeway)->toBe($before);
        }
    );

    it('reports an unverified or missing email_verified claim as unverified', function (mixed $emailVerified): void {
        expect($this->provider->verify($this->tokens->sign(['email_verified' => $emailVerified]))->emailVerified)
            ->toBeFalse();
    })->with([
        'false' => [false],
        'the string "true"' => ['true'],
        'missing' => [null],
    ]);

    it('rejects a token as invalid_social_token with a 422', function (array $claims): void {
        $exception = socialLoginFailure(
            InvalidSocialTokenException::class,
            fn () => $this->provider->verify($this->tokens->sign($claims)),
        );

        expect($exception->errorCode())->toBe('invalid_social_token')
            ->and($exception->statusCode())->toBe(422);
    })->with([
        'issued for another client' => [['aud' => 'another-client.apps.googleusercontent.com']],
        'issued for several audiences' => [['aud' => [GoogleTokens::CLIENT_ID, 'another-client']]],
        'expired' => [['iat' => time() - 7200, 'exp' => time() - 3600]],
        'not yet valid' => [['nbf' => time() + 3600]],
        'without an expiry' => [['exp' => null]],
        'from another issuer' => [['iss' => 'https://issuer.example.com']],
        'without a subject' => [['sub' => null]],
        'without an email' => [['email' => null]],
    ]);

    it('rejects a token signed by a key Google did not publish under that key id', function (): void {
        $forged = (new GoogleTokens())->sign();

        socialLoginFailure(InvalidSocialTokenException::class, fn () => $this->provider->verify($forged));
    });

    it('rejects malformed tokens without raising anything else', function (string $token): void {
        socialLoginFailure(InvalidSocialTokenException::class, fn () => $this->provider->verify($token));
    })->with([
        'not a JWT' => ['not-a-token'],
        'empty' => [''],
        'unsigned (alg none)' => [
            JWT::urlsafeB64Encode('{"alg":"none","kid":"test-key"}') . '.'
            . JWT::urlsafeB64Encode('{"sub":"1"}') . '.',
        ],
        'a header that is a JSON list' => [JWT::urlsafeB64Encode('[1]') . '.' . JWT::urlsafeB64Encode('{}') . '.c2ln'],
        'a key id that is an object' => [
            JWT::urlsafeB64Encode('{"alg":"RS256","kid":{"a":1}}') . '.' . JWT::urlsafeB64Encode('{}') . '.c2ln',
        ],
    ]);

    it('refuses to check any token while the client id is missing, without fetching Google\'s keys', function (
        mixed $clientId,
    ): void {
        Config::set('social-login.google.client_id', $clientId);

        expect(fn () => $this->provider->verify($this->tokens->sign()))
            ->toThrow(LogicException::class, 'Set GOOGLE_CLIENT_ID');

        $this->http->assertNothingSent();
    })->with([
        'unset' => [null],
        'empty' => [''],
    ]);

    it('fetches Google\'s signing keys once and reuses them while Google says they are fresh', function (): void {
        $this->provider->verify($this->tokens->sign());
        $this->provider->verify($this->tokens->sign());

        $this->http->assertSentCount(1);
    });

    it('answers social_provider_unavailable with a 503 when Google\'s keys cannot be fetched', function (): void {
        $http = new Factory();
        $http->fake([GoogleSigningKeys::JWKS_URL => Factory::response('', 500)]);
        $provider = new GoogleProvider(new GoogleSigningKeys($http, new Repository(new ArrayStore())));

        $exception = socialLoginFailure(
            SocialProviderUnavailableException::class,
            fn () => $provider->verify($this->tokens->sign()),
        );

        expect($exception->errorCode())->toBe('social_provider_unavailable')
            ->and($exception->statusCode())->toBe(503);
    });
});

describe('SocialProviderRegistry stub', function (): void {
    it('answers social_provider_unknown with a 404 for a provider the config does not list', function (
        string $provider,
    ): void {
        $exception = socialLoginFailure(
            SocialProviderUnknownException::class,
            fn () => $this->app->make(SocialProviderRegistry::class)->resolve($provider),
        );

        expect($exception->errorCode())->toBe('social_provider_unknown')
            ->and($exception->statusCode())->toBe(404);
    })->with([
        'unlisted' => ['apple'],
        'empty' => [''],
        'a config path' => ['google.client_id'],
    ]);

    it('fails loudly when a configured provider does not implement SocialProvider', function (): void {
        Config::set('social-login.providers.broken', stdClass::class);

        expect(fn () => $this->app->make(SocialProviderRegistry::class)->resolve('broken'))
            ->toThrow(LogicException::class, 'stdClass must implement');
    });
});

describe('SocialLoginAction stub', function (): void {
    beforeEach(function (): void {
        User::createTable();
        StubLoader::migrateSocialAccountsTable();

        $this->request = Request::create('/api/auth/social/google', 'POST');
        $this->request->setLaravelSession(new Store('test', new ArraySessionHandler(1)));
    });

    $signIn = function (string $token, LoginByUserAction|null $login = null, string $provider = 'google'): User {
        $this->login = $login ?? new LoginByUserAction();

        return (new SocialLoginAction(
            $this->request,
            $this->app->make(SocialProviderRegistry::class),
            $this->login,
        ))->execute(new SocialLoginDto($provider, $token));
    };

    it(
        'creates the user on a first sign-in, with Google\'s name and email and no usable password',
        function () use ($signIn): void {
            $user = $signIn->call($this, $this->tokens->sign(['email' => 'Demo@Example.com']));

            $stored = User::query()->sole();
            $account = SocialAccount::query()->sole();

            expect($user->is($stored))->toBeTrue()
                ->and($stored->name)->toBe('Demo User')
                ->and($stored->email)->toBe('demo@example.com')
                ->and($stored->email_verified_at)->not->toBeNull()
                ->and(Hash::isHashed($stored->password))->toBeTrue()
                ->and($account->user_id)->toBe($stored->id)
                ->and($account->provider)->toBe('google')
                ->and($account->provider_user_id)->toBe('100000000000000000001')
                ->and($this->login->gatedEmails)->toBe(['demo@example.com']);
        }
    );

    it('names a new user after the email when Google sends no name', function () use ($signIn): void {
        $user = $signIn->call($this, $this->tokens->sign(['name' => null]));

        expect($user->name)->toBe('demo');
    });

    it('signs a returning user in by Google account id, whatever the email now says', function () use ($signIn): void {
        $first = $signIn->call($this, $this->tokens->sign());

        $again = $signIn->call($this, $this->tokens->sign([
            'email' => 'renamed@example.com',
            'email_verified' => false,
        ]));

        expect($again->is($first))->toBeTrue()
            ->and(User::query()->count())->toBe(1)
            ->and(SocialAccount::query()->count())->toBe(1);
    });

    it('links an existing user whose email Google verified, without creating another', function () use ($signIn): void {
        $existing = User::make('demo@example.com', 'Existing Demo');

        $user = $signIn->call($this, $this->tokens->sign(['email' => 'DEMO@example.com']));

        expect($user->is($existing))->toBeTrue()
            ->and($user->name)->toBe('Existing Demo')
            ->and(User::query()->count())->toBe(1)
            ->and(SocialAccount::query()->sole()->user_id)->toBe($existing->id);
    });

    it(
        'answers social_email_not_verified with a 422 and links or creates nothing when Google has not verified the email',
        function (bool $userExists) use ($signIn): void {
            if ($userExists) {
                User::make('demo@example.com');
            }

            $login = new LoginByUserAction();

            $exception = socialLoginFailure(
                SocialEmailNotVerifiedException::class,
                fn () => $signIn->call($this, $this->tokens->sign(['email_verified' => false]), $login),
            );

            expect($exception->errorCode())->toBe('social_email_not_verified')
                ->and($exception->statusCode())->toBe(422)
                ->and(SocialAccount::query()->count())->toBe(0)
                ->and(User::query()->count())->toBe($userExists ? 1 : 0)
                ->and($login->gatedEmails)->toBe([]);
        }
    )->with([
        'existing user' => [true],
        'new user' => [false],
    ]);

    it(
        'hands a new user with 2FA the challenge from LoginByUserAction::execute(), keeping the account it created',
        function () use ($signIn): void {
            $login = new LoginByUserAction(twoFactorEmails: ['demo@example.com']);

            socialLoginFailure(
                TwoFactorChallengeException::class,
                fn () => $signIn->call($this, $this->tokens->sign(), $login),
            );

            expect($login->gatedEmails)->toBe(['demo@example.com'])
                ->and($login->transactionLevels)->toBe([0])
                ->and(User::query()->count())->toBe(1)
                ->and(SocialAccount::query()->count())->toBe(1);
        }
    );

    it(
        'challenges an existing 2FA user matched by email without linking, and links once a sign-in gets through',
        function () use ($signIn): void {
            $existing = User::make('demo@example.com', 'Existing Demo');
            $challenging = new LoginByUserAction(twoFactorEmails: ['demo@example.com']);

            socialLoginFailure(
                TwoFactorChallengeException::class,
                fn () => $signIn->call($this, $this->tokens->sign(), $challenging),
            );

            expect($challenging->gatedEmails)->toBe(['demo@example.com'])
                ->and($challenging->transactionLevels)->toBe([0])
                ->and(SocialAccount::query()->count())->toBe(0);

            $user = $signIn->call($this, $this->tokens->sign());

            expect($user->is($existing))->toBeTrue()
                ->and(SocialAccount::query()->sole()->user_id)->toBe($existing->id);
        }
    );

    it(
        'signs in the user a concurrent first sign-in created and linked, instead of failing on the duplicate',
        function () use ($signIn): void {
            $concurrent = null;

            DB::listen(function (QueryExecuted $query) use (&$concurrent): void {
                if ($concurrent !== null || ! str_contains($query->sql, 'from "users" where "email"')) {
                    return;
                }

                $concurrent = User::make('demo@example.com', 'Concurrent Demo');
                $account = new SocialAccount();
                $account->user_id = $concurrent->id;
                $account->provider = 'google';
                $account->provider_user_id = '100000000000000000001';
                $account->saveOrFail();
            });

            $user = $signIn->call($this, $this->tokens->sign());

            expect($concurrent)->not->toBeNull()
                ->and($user->is($concurrent))->toBeTrue()
                ->and(User::query()->count())->toBe(1)
                ->and(SocialAccount::query()->count())->toBe(1)
                ->and($this->login->gatedEmails)->toBe(['demo@example.com']);
        }
    );

    it('verifies nothing for an unknown provider', function () use ($signIn): void {
        socialLoginFailure(
            SocialProviderUnknownException::class,
            fn () => $signIn->call($this, $this->tokens->sign(), null, 'apple'),
        );

        $this->http->assertNothingSent();
    });

    it('refuses a request without a session before checking the token', function () use ($signIn): void {
        $this->request = Request::create('/api/auth/social/google', 'POST');

        socialLoginFailure(UnauthenticatedException::class, fn () => $signIn->call($this, $this->tokens->sign()));

        $this->http->assertNothingSent();
    });

    it('rejects an invalid token before touching any user', function () use ($signIn): void {
        User::make('demo@example.com');

        socialLoginFailure(
            InvalidSocialTokenException::class,
            fn () => $signIn->call($this, $this->tokens->sign(['aud' => 'another-client'])),
        );

        expect(SocialAccount::query()->count())->toBe(0);
    });

    it('never logs the user in itself', function (): void {
        expect(
            (string) file_get_contents(SocialLoginInstaller::stubDirectory() . '/Auth/Actions/SocialLoginAction.stub')
        )
            ->not->toMatch('/->login\(/')
            ->not->toContain('executeAfterChallenge');
    });
});
