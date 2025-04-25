<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TwoFactorAuthenticatable extends Authenticatable
{
    public const TWO_FACTOR_AUTH_SECRET_COLUMN_NAME = 'two_factor_auth_secret';

    public const TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME = 'two_factor_auth_activated_at';

    public function hasTwoFactorAuthenticationEnabled(): bool
    {
        return (bool) $this->getAttribute(self::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME);
    }

    public function hasTwoFactorAuthenticationConfigured(): bool
    {
        return $this->getTwoFactorAuthSecret()
            && $this->getAttribute(self::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME);
    }

    public function getTwoFactorAuthSecret(): string|null
    {
        /** @var string|null $secret */
        $secret = $this->getAttribute(self::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME);

        return $secret;
    }

    public function hasTwoFactorAuthenticationExpired(): bool
    {
        $isTwoFactorAuthenticationEternal = config('google2fa.lifetime') == 0;

        /** @var Carbon|null $activatedAt */
        $activatedAt = $this->getAttribute(self::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME);

        return ! $isTwoFactorAuthenticationEternal
            && $activatedAt
                ?->toImmutable()
                ->addMinutes((int) config('google2fa.lifetime'))
                ->lessThan(now());
    }

    public function getAuthenticatableHolderIdentifier(): string
    {
        return $this->email;
    }

    public function clearTwoFactorAuthenticationCredentials(): void
    {
        $this->update([
             self::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME => null,
        ]);
    }
}
