<?php

declare(strict_types=1);
use Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable;
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
requireRenderedTwoFactorAuthenticatableStub('Google2FA/Auth/DataTransferObjects/TwoFactorTokenPayloadDto.stub');
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
});
