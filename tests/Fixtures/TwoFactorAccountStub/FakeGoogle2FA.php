<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

/**
 * Stands in for `PragmaRX\Google2FALaravel\Google2FA`, which this package does
 * not require: each code in `$validCodes` matches the timestep it maps to.
 */
final class FakeGoogle2FA
{
    public string $nextSecret = 'GENERATED-SECRET';

    /**
     * @param array<string, int> $validCodes
     */
    public function __construct(private readonly array $validCodes = [])
    {
    }

    public function verifyKeyNewer(string $secret, string $key, int $oldTimestamp): int|false
    {
        $timestep = $this->validCodes[$key] ?? null;

        return $timestep !== null && $timestep > $oldTimestamp ? $timestep : false;
    }

    public function getWindow(): int
    {
        return 1;
    }

    public function getKeyRegeneration(): int
    {
        return 30;
    }

    public function generateSecretKey(): string
    {
        return $this->nextSecret;
    }

    public function getQRCodeInline(string $company, string $holder, string $secret): string
    {
        return "<svg>{$holder}:{$secret}</svg>";
    }
}
