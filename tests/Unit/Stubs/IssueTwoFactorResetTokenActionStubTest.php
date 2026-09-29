<?php

declare(strict_types=1);

use Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub\FakeUser;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub\IssueTwoFactorResetTokenAction;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub\PasswordValidatorAction;
use Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub\TwoFactorReason;

/**
 * IssueTwoFactorResetTokenAction.stub becomes Eloquent code in the consuming
 * project (it calls the real `User::create2faToken()`), so it cannot be
 * exercised against a database from this package - see
 * laravel-package-testing: no Eloquent, no database here. Rendered here into
 * a private test namespace (FakeUser stands in for the real User model and
 * records the TTL its create2faToken() receives; the fixture
 * PasswordValidatorAction stands in for the real password check) the same
 * way IssueTwoFactorChallengeActionStubTest.php does, so the
 * `challenge_ttl_minutes` fallback is exercised behaviorally instead of by
 * reading the stub's source text.
 */
function renderIssueTwoFactorResetTokenActionStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/' . $relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain\Actions;',
            'namespace Lightit\Authentication\Domain\Enums;',
            "use Lightit\Authentication\Domain\Enums\TwoFactorReason;\n",
            'use Lightit\Users\Domain\Models\User;',
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub;',
            'namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub;',
            '',
            // User is aliased to FakeUser, whose create2faToken() records the TTL it
            // receives instead of encrypting a real token.
            'use Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub\FakeUser as User;',
        ],
        $contents,
    );
}

function requireRenderedResetTokenActionStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir() . '/issue-two-factor-reset-token-action-stub-' . md5($relativePath) . '.php';
    file_put_contents($tempFile, renderIssueTwoFactorResetTokenActionStub($relativePath));
    require_once $tempFile;
}

requireRenderedResetTokenActionStub('Shared/Auth/Enums/TwoFactorReason.stub');
requireRenderedResetTokenActionStub('Google2FA/Auth/Actions/IssueTwoFactorResetTokenAction.stub');

describe('IssueTwoFactorResetTokenAction stub', function (): void {
    it('falls back to a 15 minute TTL when google2fa.challenge_ttl_minutes is not configured', function (): void {
        $google2fa = config('google2fa');
        unset($google2fa['challenge_ttl_minutes']);
        config(['google2fa' => $google2fa]);

        $action = new IssueTwoFactorResetTokenAction(new PasswordValidatorAction());
        $user = new FakeUser();

        $result = $action->execute($user, 'irrelevant-password');

        expect($user->capturedTtlMinutes)->toBe(15)
            ->and($user->capturedFactorReason)->toBe(TwoFactorReason::ResetRequired)
            ->and($result['expires_in'])->toBe(900);
    });
});
