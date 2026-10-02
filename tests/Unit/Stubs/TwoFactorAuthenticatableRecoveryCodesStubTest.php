<?php

declare(strict_types=1);
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConsumerUser;
use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\TwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\TwoFactorReason;

/**
 * TwoFactorAuthenticatable.stub is a template for the consuming app - it
 * hardcodes `Lightit\...` namespaces this package never loads directly.
 * Rendered here into a private test namespace, the same way
 * IssueTwoFactorChallengeActionStubTest does, so `getRecoveryCodes()`'s null/non-array
 * handling and `create2faToken()`'s config reads are exercised without a full
 * consumer app or a database.
 */
function renderTwoFactorAuthenticatableStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/' . $relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain;',
            'namespace Lightit\Authentication\Domain\Enums;',
            'namespace Lightit\Authentication\Domain\DataTransferObjects;',
            "use Lightit\Authentication\Domain\DataTransferObjects\TwoFactorTokenPayloadDto;\n",
            "use Lightit\Authentication\Domain\Enums\TwoFactorReason;\n",
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;',
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;',
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;',
            '',
            '',
        ],
        $contents,
    );
}

function requireRenderedTwoFactorAuthenticatableStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir() . '/two-factor-authenticatable-stub-' . md5($relativePath) . '.php';
    file_put_contents($tempFile, renderTwoFactorAuthenticatableStub($relativePath));
    require_once $tempFile;
}

requireRenderedTwoFactorAuthenticatableStub('Shared/Auth/Enums/TwoFactorReason.stub');
requireRenderedTwoFactorAuthenticatableStub('Shared/Auth/DataTransferObjects/TwoFactorTokenPayloadDto.stub');
requireRenderedTwoFactorAuthenticatableStub('Shared/Auth/TwoFactorAuthenticatable.stub');

if (! class_exists(ConcreteTwoFactorAuthenticatable::class)) {
    eval(
        'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;'
        . 'final class ConcreteTwoFactorAuthenticatable extends TwoFactorAuthenticatable {}'
    );
}

describe('TwoFactorAuthenticatable stub getRecoveryCodes()', function (): void {
    it('returns an empty array when the column is null', function (): void {
        $user = new ConcreteTwoFactorAuthenticatable();
        $user->setRawAttributes(['recovery_codes' => null]);

        expect($user->getRecoveryCodes())->toBe([]);
    });

    it('returns an empty array when the column decodes to a non-array JSON value', function (): void {
        $user = new ConcreteTwoFactorAuthenticatable();
        $user->setRawAttributes(['recovery_codes' => json_encode('not-an-array')]);

        expect($user->getRecoveryCodes())->toBe([]);
    });

    it('decodes a JSON-encoded array of recovery codes', function (): void {
        $user = new ConcreteTwoFactorAuthenticatable();
        $user->setRawAttributes(['recovery_codes' => json_encode(['a', 'b'])]);

        expect($user->getRecoveryCodes())->toBe(['a', 'b']);
    });
});

describe('TwoFactorAuthenticatable stub create2faToken()', function (): void {
    beforeEach(function (): void {
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
    });

    it(
        'does not throw when google2fa.mandatory and challenge_ttl_minutes are entirely absent, as in a vendor-shaped config',
        function (): void {
            config(['google2fa' => null]);

            $user = new ConcreteTwoFactorAuthenticatable();
            $user->setRawAttributes(['id' => 1]);

            expect(fn () => $user->create2faToken(15, TwoFactorReason::VerificationRequired))
                ->not->toThrow(InvalidArgumentException::class);
        }
    );

    it(
        'defaults the encrypted payload\'s mandatory flag to true when google2fa.mandatory is absent',
        function (): void {
            config(['google2fa' => null]);

            $user = new ConcreteTwoFactorAuthenticatable();
            $user->setRawAttributes(['id' => 1]);

            $token = $user->create2faToken(15, TwoFactorReason::VerificationRequired);

            /** @var array{mandatory: bool} $payload */
            $payload = Crypt::decrypt($token);

            expect($payload['mandatory'])->toBeTrue();
        }
    );
});

/**
 * The users table as the consumer ends up with it: a bare table, then the package's own
 * migration stub run against it, so the secret column's real type is what is exercised.
 */
function createUsersTableWithTwoFactorColumns(): void
{
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });

    $migrationFile = sys_get_temp_dir() . '/two-factor-authenticatable-stub-migration.php';
    file_put_contents($migrationFile, str_replace(
        'use Lightit\Authentication\Domain\TwoFactorAuthenticatable;',
        'use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\TwoFactorAuthenticatable;',
        (string) file_get_contents(
            __DIR__ . '/../../../database/migrations/add_two_factor_authentication_columns.stub'
        ),
    ));

    (require $migrationFile)->up();
}

describe('TwoFactorAuthenticatable stub, persisted', function (): void {
    beforeEach(function (): void {
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        createUsersTableWithTwoFactorColumns();
    });

    it(
        'stores the TOTP secret encrypted and reads it back in plaintext, next to the User\'s own casts()',
        function (): void {
            $user = new ConsumerUser();
            $user->email = 'user@example.com';
            $user->email_verified_at = '2026-01-02 03:04:05';
            $user->setAttribute(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME, 'JBSWY3DPEHPK3PXP');
            $user->saveOrFail();
    
            $stored = (string) DB::table('users')->value(
                TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME
            );
            $reloaded = ConsumerUser::query()->findOrFail($user->id);
    
            expect($stored)->not->toContain('JBSWY3DPEHPK3PXP')
                ->and(Crypt::decryptString($stored))->toBe('JBSWY3DPEHPK3PXP')
                ->and($reloaded->getTwoFactorAuthSecret())->toBe('JBSWY3DPEHPK3PXP')
                ->and($reloaded->hasTwoFactorAuthenticationSecretStored())->toBeTrue()
                ->and($reloaded->email_verified_at)->toBeInstanceOf(CarbonImmutable::class);
        }
    );

    it('keeps the cast a User declares for the secret column itself', function (): void {
        $user = new class() extends ConsumerUser {
            protected function casts(): array
            {
                return [TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME => 'string'];
            }
        };
        $user->email = 'user@example.com';
        $user->setAttribute(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME, 'JBSWY3DPEHPK3PXP');
        $user->saveOrFail();

        expect(DB::table('users')->value(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME))
            ->toBe('JBSWY3DPEHPK3PXP');
    });
});
