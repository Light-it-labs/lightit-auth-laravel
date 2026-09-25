<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stands in for `Lightit\Users\Domain\Models\User` when TwoFactorLoginGate.stub
 * is rendered into this test namespace (see TwoFactorLoginGateStubTest) - the
 * three methods the gate's 2FA branching calls, plus `Authenticatable` so the
 * same instance can also be logged into a real `SessionGuard` to exercise the
 * gate's session teardown.
 */
final class FakeUser implements Authenticatable
{
    public function __construct(
        private readonly bool $hasSecretStored,
        private readonly bool $hasConfigured,
        private readonly int|string $id = 1,
    ) {}

    public function hasTwoFactorAuthenticationConfigured(): bool
    {
        return $this->hasConfigured;
    }

    public function hasTwoFactorAuthenticationSecretStored(): bool
    {
        return $this->hasSecretStored;
    }

    public function create2faToken(int $ttlInMinutes, TwoFactorReason $factorReason): string
    {
        return $factorReason->value.':'.$ttlInMinutes;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // No remember-me support needed for this fixture.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
