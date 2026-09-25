<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorLoginGateStub;

/**
 * Stands in for `Lightit\Users\Domain\Models\User` when TwoFactorLoginGate.stub
 * is rendered into this test namespace (see TwoFactorLoginGateStubTest) -
 * only the three methods the gate actually calls.
 */
final class FakeUser
{
    public function __construct(
        private readonly bool $hasSecretStored,
        private readonly bool $hasConfigured,
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
}
