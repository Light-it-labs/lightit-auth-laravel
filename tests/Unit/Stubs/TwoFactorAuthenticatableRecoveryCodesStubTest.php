<?php

declare(strict_types=1);

/**
 * TwoFactorAuthenticatable.stub is a template for the consuming app - it
 * hardcodes `Lightit\...` namespaces this package never loads directly.
 * Rendered here into a private test namespace, the same way
 * TwoFactorLoginGateStubTest does, so `getRecoveryCodes()`'s null/non-array
 * handling is exercised without a full consumer app or a database.
 */
function renderTwoFactorAuthenticatableStub(): string
{
    $contents = (string) file_get_contents(
        __DIR__.'/../../../src/Stubs/Google2FA/Auth/TwoFactorAuthenticatable.stub'
    );

    return str_replace(
        'namespace Lightit\Authentication\Domain;',
        'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;',
        $contents,
    );
}

$tempFile = sys_get_temp_dir().'/two-factor-authenticatable-stub.php';
file_put_contents($tempFile, renderTwoFactorAuthenticatableStub());
require_once $tempFile;

if (! class_exists(Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable::class)) {
    eval(
        'namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;'
        .'final class ConcreteTwoFactorAuthenticatable extends TwoFactorAuthenticatable {}'
    );
}

describe('TwoFactorAuthenticatable stub getRecoveryCodes()', function (): void {
    it('returns an empty array when the column is null', function (): void {
        $user = new Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable;
        $user->setRawAttributes(['recovery_codes' => null]);

        expect($user->getRecoveryCodes())->toBe([]);
    });

    it('returns an empty array when the column decodes to a non-array JSON value', function (): void {
        $user = new Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable;
        $user->setRawAttributes(['recovery_codes' => json_encode('not-an-array')]);

        expect($user->getRecoveryCodes())->toBe([]);
    });

    it('decodes a JSON-encoded array of recovery codes', function (): void {
        $user = new Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub\ConcreteTwoFactorAuthenticatable;
        $user->setRawAttributes(['recovery_codes' => json_encode(['a', 'b'])]);

        expect($user->getRecoveryCodes())->toBe(['a', 'b']);
    });
});
