<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Schema;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\ConfirmTwoFactorAuthenticationAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\ConsumeRecoveryCodeAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\DisableTwoFactorAuthenticationAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\EnableTwoFactorAuthenticationAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeGoogle2FA;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeUser;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\GenerateQRCodeAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\GenerateRecoveryCodesAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\PasswordValidatorAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\RegenerateRecoveryCodesAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\ResetTwoFactorAuthenticationAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\StubLoader;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\TwoFactorAuthException;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\TwoFactorStatusResource;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\VerifyOtpAction;
use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\VerifyTwoFactorCodeAction;

StubLoader::load(
    'Shared/Auth/TwoFactorAuthenticatable.stub',
    'Google2FA/Auth/Exceptions/TwoFactorAuthException.stub',
    'Google2FA/Auth/TwoFactorAttemptLimiter.stub',
    'Google2FA/Auth/DataTransferObjects/TwoFactorEnrollmentDto.stub',
    'Google2FA/Auth/Actions/PasswordValidatorAction.stub',
    'Google2FA/Auth/Actions/GenerateQRCodeAction.stub',
    'Google2FA/Auth/Actions/GenerateRecoveryCodesAction.stub',
    'Google2FA/Auth/Actions/VerifyOtpAction.stub',
    'Google2FA/Auth/Actions/ConsumeRecoveryCodeAction.stub',
    'Google2FA/Auth/Actions/VerifyTwoFactorCodeAction.stub',
    'Google2FA/Auth/Actions/EnableTwoFactorAuthenticationAction.stub',
    'Google2FA/Auth/Actions/ConfirmTwoFactorAuthenticationAction.stub',
    'Google2FA/Auth/Actions/RegenerateRecoveryCodesAction.stub',
    'Google2FA/Auth/Actions/ResetTwoFactorAuthenticationAction.stub',
    'Google2FA/Auth/Actions/DisableTwoFactorAuthenticationAction.stub',
    'Google2FA/Auth/Resources/TwoFactorStatusResource.stub',
);

function enrollmentFailure(callable $attempt): TwoFactorAuthException
{
    try {
        $attempt();
    } catch (TwoFactorAuthException $exception) {
        return $exception;
    }

    test()->fail('Expected a TwoFactorAuthException to be thrown.');
}

function enrollingUser(array $attributes = []): FakeUser
{
    $user = new FakeUser();
    $user->setRawAttributes([
        'id' => 'user-' . bin2hex(random_bytes(6)),
        'email' => 'user@example.com',
        'password' => Hash::make('correct-password'),
        'two_factor_auth_secret' => null,
        'two_factor_auth_activated_at' => null,
        'recovery_codes' => null,
        ...$attributes,
    ]);
    $user->setAttribute('two_factor_auth_secret', $attributes['two_factor_auth_secret'] ?? null);

    FakeUser::$rows[$user->getKey()] = $user;

    return $user;
}

function lockedRowFor(FakeUser $user, array $attributes): FakeUser
{
    $row = new FakeUser();
    $row->setRawAttributes([...$user->getAttributes(), ...$attributes]);

    if (array_key_exists('two_factor_auth_secret', $attributes)) {
        $row->setAttribute('two_factor_auth_secret', $attributes['two_factor_auth_secret']);
    }

    FakeUser::$rows[$user->getKey()] = $row;

    return $row;
}

beforeEach(function (): void {
    config([
        'hashing.bcrypt.rounds' => 4,
        'app.name' => 'Example App',
        'app.key' => 'base64:' . base64_encode(random_bytes(32)),
        'google2fa.enabled' => true,
        'google2fa.mandatory' => false,
    ]);

    FakeUser::$rows = [];
    FakeUser::$lockedKeys = [];

    $this->google2FA = new FakeGoogle2FA(['123456' => 1000]);

    $this->enable = new EnableTwoFactorAuthenticationAction(
        new PasswordValidatorAction(),
        $this->google2FA,
        new GenerateQRCodeAction($this->google2FA),
    );

    $this->confirm = new ConfirmTwoFactorAuthenticationAction(
        new VerifyOtpAction($this->google2FA),
        new GenerateRecoveryCodesAction(),
    );

    $verifyTwoFactorCode = new VerifyTwoFactorCodeAction(
        new VerifyOtpAction($this->google2FA),
        new ConsumeRecoveryCodeAction(),
    );

    $this->regenerate = new RegenerateRecoveryCodesAction(
        new PasswordValidatorAction(),
        $verifyTwoFactorCode,
        new GenerateRecoveryCodesAction(),
    );

    $this->disable = new DisableTwoFactorAuthenticationAction(
        new PasswordValidatorAction(),
        $verifyTwoFactorCode,
        new ResetTwoFactorAuthenticationAction(),
    );
});

function activeTwoFactorUser(): FakeUser
{
    return enrollingUser([
        'two_factor_auth_secret' => 'ACTIVE-SECRET',
        'two_factor_auth_activated_at' => now(),
        'recovery_codes' => json_encode([Hash::make('first-recovery-code'), Hash::make('spare-recovery-code')]),
    ]);
}

function useDatabaseCacheStore(): void
{
    Schema::create('cache', function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });
    config(['cache.default' => 'database', 'cache.stores.database.connection' => null]);
    app()->forgetInstance(RateLimiter::class);
    RateLimiterFacade::clearResolvedInstance(RateLimiter::class);
}

describe('EnableTwoFactorAuthenticationAction stub', function (): void {
    it('stores a new secret for a signed-in user and returns it with its QR code', function (): void {
        $user = enrollingUser();

        $enrollment = $this->enable->execute($user, 'correct-password');

        expect($enrollment->secret)->toBe('GENERATED-SECRET')
            ->and($enrollment->qr)->toBe('<svg>user@example.com:GENERATED-SECRET</svg>')
            ->and($user->getTwoFactorAuthSecret())->toBe('GENERATED-SECRET')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse()
            ->and($user->saves)->toBe(1);
    });

    it('rejects a wrong password with 422 instead of 401, and stores nothing', function (): void {
        $user = enrollingUser();

        $exception = enrollmentFailure(fn () => $this->enable->execute($user, 'wrong-password'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_password')
            ->and($user->getTwoFactorAuthSecret())->toBeNull()
            ->and($user->saves)->toBe(0);
    });

    it('refuses with 409 and keeps the active secret when 2FA is already enabled', function (): void {
        $user = enrollingUser([
            'two_factor_auth_secret' => 'ACTIVE-SECRET',
            'two_factor_auth_activated_at' => now(),
        ]);

        $exception = enrollmentFailure(fn () => $this->enable->execute($user, 'correct-password'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('2fa_already_configured')
            ->and($user->getTwoFactorAuthSecret())->toBe('ACTIVE-SECRET')
            ->and($user->saves)->toBe(0);
    });

    it('replaces the secret of an enrollment that was started but never confirmed', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'ABANDONED-SECRET']);

        $this->enable->execute($user, 'correct-password');

        expect($user->getTwoFactorAuthSecret())->toBe('GENERATED-SECRET');
    });

    it('writes the secret on the locked user row', function (): void {
        $user = enrollingUser();

        $this->enable->execute($user, 'correct-password');

        expect(FakeUser::$lockedKeys)->toBe([$user->getKey()]);
    });

    it('refuses with 409 when the locked row was activated after the user was loaded', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'PENDING-SECRET']);
        $row = lockedRowFor($user, ['two_factor_auth_activated_at' => now()]);

        $exception = enrollmentFailure(fn () => $this->enable->execute($user, 'correct-password'));

        expect($exception->errorCode())->toBe('2fa_already_configured')
            ->and($row->getTwoFactorAuthSecret())->toBe('PENDING-SECRET')
            ->and($row->saves)->toBe(0);
    });

    it('treats an activation date without a secret as off, and starts a fresh pending setup', function (): void {
        $user = enrollingUser(['two_factor_auth_activated_at' => now()]);

        $this->enable->execute($user, 'correct-password');

        expect($user->getTwoFactorAuthSecret())->toBe('GENERATED-SECRET')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse();
    });

    it('refuses with 409 and stores nothing while google2fa.enabled is off', function (): void {
        config(['google2fa.enabled' => false]);
        $user = enrollingUser();

        $exception = enrollmentFailure(fn () => $this->enable->execute($user, 'correct-password'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('2fa_unavailable')
            ->and($user->getTwoFactorAuthSecret())->toBeNull()
            ->and($user->saves)->toBe(0);
    });
});

describe('ConfirmTwoFactorAuthenticationAction stub', function (): void {
    it('activates 2FA with the first valid code and returns fresh recovery codes', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        $recoveryCodes = $this->confirm->execute($user, '123456');

        expect($user->hasTwoFactorAuthenticationEnabled())->toBeTrue()
            ->and($recoveryCodes)->toHaveCount(8)
            ->and($user->getRecoveryCodes())->toHaveCount(8)
            ->and(Hash::check($recoveryCodes[0], $user->getRecoveryCodes()[0]))->toBeTrue();
    });

    it('rejects a wrong code with 422 and leaves 2FA inactive', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '000000'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse()
            ->and($user->getRecoveryCodes())->toBe([]);
    });

    it('refuses with 409 when 2FA is already enabled', function (): void {
        $user = enrollingUser([
            'two_factor_auth_secret' => 'ACTIVE-SECRET',
            'two_factor_auth_activated_at' => now(),
        ]);

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('2fa_already_configured');
    });

    it('refuses with 409 when enabling was never started', function (): void {
        $exception = enrollmentFailure(fn () => $this->confirm->execute(enrollingUser(), '123456'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('2fa_not_started');
    });

    it('locks the user out with 429 after 5 attempts, even before a correct code', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            enrollmentFailure(fn () => $this->confirm->execute($user, '000000'));
        }

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->statusCode())->toBe(429)
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse();
    });

    it('keeps counting failed confirms when the cache store writes to the database', function (): void {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        config(['cache.default' => 'database', 'cache.stores.database.connection' => null]);
        app()->forgetInstance(RateLimiter::class);
        RateLimiterFacade::clearResolvedInstance(RateLimiter::class);
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            enrollmentFailure(fn () => $this->confirm->execute($user, '000000'));
        }

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->statusCode())->toBe(429)
            ->and($exception->errorCode())->toBe('too_many_attempts')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse()
            ->and(DB::table('cache')->count())->toBeGreaterThan(0);
    });

    it('rejects a code the same user already spent in this window', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);
        (new VerifyOtpAction($this->google2FA))->execute($user->getKey(), 'GENERATED-SECRET', '123456');

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->errorCode())->toBe('otp_already_used')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse();
    });

    it('activates the locked user row', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        $this->confirm->execute($user, '123456');

        expect(FakeUser::$lockedKeys)->toBe([$user->getKey()]);
    });

    it('hands out no codes when a concurrent confirm already activated the locked row', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);
        $row = lockedRowFor($user, ['two_factor_auth_activated_at' => now()]);

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->errorCode())->toBe('2fa_already_configured')
            ->and($row->getRecoveryCodes())->toBe([]);
    });

    it('rejects the code when another tab replaced the secret before the lock', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);
        $row = lockedRowFor($user, ['two_factor_auth_secret' => 'NEWER-SECRET']);

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp')
            ->and($this->google2FA->verifiedSecrets)->toBe(['GENERATED-SECRET'])
            ->and($row->hasTwoFactorAuthenticationConfigured())->toBeFalse()
            ->and($row->getRecoveryCodes())->toBe([]);
    });

    it('refuses with 409 and leaves 2FA inactive while google2fa.enabled is off', function (): void {
        config(['google2fa.enabled' => false]);
        $user = enrollingUser(['two_factor_auth_secret' => 'GENERATED-SECRET']);

        $exception = enrollmentFailure(fn () => $this->confirm->execute($user, '123456'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('2fa_unavailable')
            ->and($user->hasTwoFactorAuthenticationEnabled())->toBeFalse();
    });
});

describe('TwoFactorStatusResource stub', function (): void {
    $statusOf = static function (FakeUser $user): array {
        return (new TwoFactorStatusResource($user))->toArray(Request::create('/2fa/status'));
    };

    it('reports 2FA as on only when it is both confirmed and backed by a secret', function () use ($statusOf): void {
        expect($statusOf(activeTwoFactorUser())['enabled'])->toBeTrue()
            ->and($statusOf(enrollingUser(['two_factor_auth_secret' => 'PENDING-SECRET']))['enabled'])->toBeFalse()
            ->and($statusOf(enrollingUser(['two_factor_auth_activated_at' => now()]))['enabled'])->toBeFalse();
    });

    it('reports whether 2FA is available and mandatory from config/google2fa.php', function () use ($statusOf): void {
        expect($statusOf(enrollingUser()))->toBe(['available' => true, 'enabled' => false, 'mandatory' => false]);

        config(['google2fa.enabled' => false, 'google2fa.mandatory' => true]);

        expect($statusOf(enrollingUser()))->toBe(['available' => false, 'enabled' => false, 'mandatory' => true]);
    });
});

dataset('second-factor guarded actions', ['regenerate', 'disable']);

describe('the second factor on regenerate and disable', function (): void {
    it('rejects an empty code with 422 and changes nothing', function (string $action): void {
        $user = activeTwoFactorUser();

        $exception = enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', ''));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue()
            ->and($user->getRecoveryCodes())->toHaveCount(2);
    })->with('second-factor guarded actions');

    it('rejects a wrong authenticator code with 422 and changes nothing', function (string $action): void {
        $user = activeTwoFactorUser();

        $exception = enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', '000000'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue()
            ->and($user->getRecoveryCodes())->toHaveCount(2);
    })->with('second-factor guarded actions');

    it('rejects a wrong recovery code with 422 and keeps every recovery code', function (string $action): void {
        $user = activeTwoFactorUser();

        $exception = enrollmentFailure(
            fn () => $this->{$action}->execute($user, 'correct-password', 'not-a-recovery-code')
        );

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_otp')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue()
            ->and($user->getRecoveryCodes())->toHaveCount(2);
    })->with('second-factor guarded actions');

    it(
        'checks the password before the code, and does not spend the code on a wrong password',
        function (string $action): void {
            $user = activeTwoFactorUser();
    
            $exception = enrollmentFailure(fn () => $this->{$action}->execute($user, 'wrong-password', '123456'));
    
            expect($exception->errorCode())->toBe('invalid_password')
                ->and($this->google2FA->verifiedSecrets)->toBe([]);
        }
    )->with('second-factor guarded actions');

    it('locks the user out with 429 after 5 wrong codes, even before a correct one', function (string $action): void {
        $user = activeTwoFactorUser();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            enrollmentFailure(
                fn () => $this->{$action}->execute(
                    $user,
                    'correct-password',
                    $attempt % 2 === 0 ? '000000' : 'wrong-code'
                )
            );
        }

        $exception = enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', '123456'));

        expect($exception->statusCode())->toBe(429)
            ->and($exception->errorCode())->toBe('too_many_attempts')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue()
            ->and($user->getRecoveryCodes())->toHaveCount(2);
    })->with('second-factor guarded actions');

    it(
        'keeps counting wrong recovery codes when the cache store writes to the database',
        function (string $action): void {
            useDatabaseCacheStore();
            $user = activeTwoFactorUser();
    
            for ($attempt = 0; $attempt < 5; $attempt++) {
                enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', 'wrong-code'));
            }
    
            $exception = enrollmentFailure(
                fn () => $this->{$action}->execute($user, 'correct-password', 'spare-recovery-code')
            );
    
            expect($exception->statusCode())->toBe(429)
                ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue()
                ->and($user->getRecoveryCodes())->toHaveCount(2)
                ->and(DB::table('cache')->count())->toBeGreaterThan(0);
        }
    )->with('second-factor guarded actions');

    it('rejects an authenticator code the same user already spent in this window', function (string $action): void {
        $user = activeTwoFactorUser();
        (new VerifyOtpAction($this->google2FA))->execute($user->getKey(), 'ACTIVE-SECRET', '123456');

        $exception = enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', '123456'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('otp_already_used')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue();
    })->with('second-factor guarded actions');

    it('clears the lockout count once a valid code is accepted', function (string $action): void {
        $user = activeTwoFactorUser();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            enrollmentFailure(fn () => $this->{$action}->execute($user, 'correct-password', '000000'));
        }

        $this->{$action}->execute($user, 'correct-password', '123456');

        expect(RateLimiterFacade::attempts('2fa-user-attempts|' . $user->getKey()))->toBe(0);
    })->with('second-factor guarded actions');
});

describe('RegenerateRecoveryCodesAction stub', function (): void {
    it('replaces the recovery codes when the password and a live authenticator code match', function (): void {
        $user = activeTwoFactorUser();
        $previousHashes = $user->getRecoveryCodes();

        $recoveryCodes = $this->regenerate->execute($user, 'correct-password', '123456');

        expect($recoveryCodes)->toHaveCount(8)
            ->and(array_intersect($previousHashes, $user->getRecoveryCodes()))->toBe([])
            ->and(Hash::check($recoveryCodes[0], $user->getRecoveryCodes()[0]))->toBeTrue()
            ->and($this->google2FA->verifiedSecrets)->toBe(['ACTIVE-SECRET']);
    });

    it('accepts an unused recovery code instead, and that code no longer works afterwards', function (): void {
        $user = activeTwoFactorUser();

        $recoveryCodes = $this->regenerate->execute($user, 'correct-password', 'spare-recovery-code');

        expect($recoveryCodes)->toHaveCount(8)
            ->and(FakeUser::$lockedKeys)->toBe([$user->getKey()])
            ->and($this->google2FA->verifiedSecrets)->toBe([]);

        $exception = enrollmentFailure(
            fn () => $this->regenerate->execute($user, 'correct-password', 'spare-recovery-code')
        );

        expect($exception->errorCode())->toBe('invalid_otp');
    });

    it('rejects a wrong password with 422 and keeps the current codes', function (): void {
        $user = activeTwoFactorUser();

        $exception = enrollmentFailure(fn () => $this->regenerate->execute($user, 'wrong-password', '123456'));

        expect($exception->statusCode())->toBe(422)
            ->and($exception->errorCode())->toBe('invalid_password')
            ->and($user->getRecoveryCodes())->toHaveCount(2);
    });

    it('refuses with 409 when 2FA is not on, before checking the password', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'PENDING-SECRET']);

        $exception = enrollmentFailure(fn () => $this->regenerate->execute($user, 'wrong-password', '123456'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('cannot_regenerate_2fa_unconfigured')
            ->and($user->getRecoveryCodes())->toBe([]);
    });
});

describe('DisableTwoFactorAuthenticationAction stub', function (): void {
    it('turns 2FA off when the password and a live authenticator code match', function (): void {
        $user = activeTwoFactorUser();

        $this->disable->execute($user, 'correct-password', '123456');

        expect($user->hasTwoFactorAuthenticationConfigured())->toBeFalse()
            ->and($user->getTwoFactorAuthSecret())->toBeNull()
            ->and($user->getRecoveryCodes())->toBe([]);
    });

    it('turns 2FA off with an unused recovery code instead of an authenticator code', function (): void {
        $user = activeTwoFactorUser();

        $this->disable->execute($user, 'correct-password', 'spare-recovery-code');

        expect($user->hasTwoFactorAuthenticationConfigured())->toBeFalse()
            ->and(FakeUser::$lockedKeys)->toBe([$user->getKey()])
            ->and($this->google2FA->verifiedSecrets)->toBe([]);
    });

    it('refuses with 403 while 2FA is mandatory, before checking the password or the code', function (): void {
        config(['google2fa.mandatory' => true]);
        $user = activeTwoFactorUser();

        $exception = enrollmentFailure(fn () => $this->disable->execute($user, 'wrong-password', '000000'));

        expect($exception->statusCode())->toBe(403)
            ->and($exception->errorCode())->toBe('disable_forbidden')
            ->and($user->hasTwoFactorAuthenticationConfigured())->toBeTrue();
    });

    it('refuses with 409 for a setup that was started but never confirmed', function (): void {
        $user = enrollingUser(['two_factor_auth_secret' => 'PENDING-SECRET']);

        $exception = enrollmentFailure(fn () => $this->disable->execute($user, 'correct-password', '123456'));

        expect($exception->statusCode())->toBe(409)
            ->and($exception->errorCode())->toBe('cannot_disable_2fa_unconfigured')
            ->and($user->getTwoFactorAuthSecret())->toBe('PENDING-SECRET');
    });
});
