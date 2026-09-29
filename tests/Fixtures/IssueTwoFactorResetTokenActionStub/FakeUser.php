<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub;

final class FakeUser
{
    public int|null $capturedTtlMinutes = null;

    public TwoFactorReason|null $capturedFactorReason = null;

    public function create2faToken(int $ttlInMinutes, TwoFactorReason $factorReason): string
    {
        $this->capturedTtlMinutes = $ttlInMinutes;
        $this->capturedFactorReason = $factorReason;

        return 'fake-2fa-token';
    }
}
