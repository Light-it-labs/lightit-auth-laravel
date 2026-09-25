<?php

declare(strict_types=1);

use Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub\FakeUser;
use Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub\TwoFactorChallengeException;
use Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub\TwoFactorLoginGate;

/**
 * TwoFactorLoginGate.stub is a template for the consuming app - it hardcodes
 * `Lightit\Users\Domain\Models\User` and lives under `Lightit\` namespaces
 * this package never loads directly. Rendered here into a private test
 * namespace (FakeUser stands in for the real User model) so its branching
 * logic is exercised without a full consumer app.
 */
function renderTwoFactorGateStub(string $relativePath): string
{
    $contents = (string) file_get_contents(__DIR__.'/../../../src/Stubs/Google2FA/Auth/'.$relativePath);

    return str_replace(
        [
            'namespace Lightit\Authentication\Domain\Actions;',
            'namespace Lightit\Authentication\Domain\Enums;',
            'namespace Lightit\Authentication\Domain\Exceptions;',
            "use Lightit\Authentication\Domain\Enums\TwoFactorReason;\n",
            "use Lightit\Authentication\Domain\Exceptions\TwoFactorChallengeException;\n",
            'use Lightit\Users\Domain\Models\User;',
        ],
        [
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub;',
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub;',
            'namespace Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub;',
            '',
            '',
            'use Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub\FakeUser as User;',
        ],
        $contents,
    );
}

function requireRenderedGateStub(string $relativePath): void
{
    $tempFile = sys_get_temp_dir().'/two-factor-login-gate-stub-'.md5($relativePath).'.php';
    file_put_contents($tempFile, renderTwoFactorGateStub($relativePath));
    require_once $tempFile;
}

requireRenderedGateStub('Enums/TwoFactorReason.stub');
requireRenderedGateStub('Exceptions/TwoFactorChallengeException.stub');
requireRenderedGateStub('Actions/TwoFactorLoginGate.stub');

describe('TwoFactorLoginGate stub', function (): void {
    beforeEach(function (): void {
        config(['google2fa.challenge_ttl_minutes' => 15]);
    });

    it('does nothing when 2FA is disabled', function (): void {
        config(['google2fa.enabled' => false]);

        $gate = new TwoFactorLoginGate;

        $gate->guardAgainstChallenge(new FakeUser(hasSecretStored: true, hasConfigured: true));
    })->throwsNoExceptions();

    it('throws a setup-required challenge when 2FA is mandatory and the user has no secret', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => true]);

        $gate = new TwoFactorLoginGate;

        try {
            $gate->guardAgainstChallenge(new FakeUser(hasSecretStored: false, hasConfigured: false));
            test()->fail('Expected a TwoFactorChallengeException to be thrown.');
        } catch (TwoFactorChallengeException $exception) {
            expect($exception->tokenType)->toBe('setup_required');
            expect($exception->expiresIn)->toBe(15 * 60);
        }
    });

    it('throws a verification-required challenge when the user already has a secret stored', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);

        $gate = new TwoFactorLoginGate;

        try {
            $gate->guardAgainstChallenge(new FakeUser(hasSecretStored: true, hasConfigured: true));
            test()->fail('Expected a TwoFactorChallengeException to be thrown.');
        } catch (TwoFactorChallengeException $exception) {
            expect($exception->tokenType)->toBe('verification_required');
        }
    });

    it('does nothing when 2FA is optional and the user has not configured it', function (): void {
        config(['google2fa.enabled' => true, 'google2fa.mandatory' => false]);

        $gate = new TwoFactorLoginGate;

        $gate->guardAgainstChallenge(new FakeUser(hasSecretStored: false, hasConfigured: false));
    })->throwsNoExceptions();
});
