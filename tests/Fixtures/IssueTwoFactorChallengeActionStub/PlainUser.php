<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorChallengeActionStub;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stands in for a consumer's `User` model that was never made to extend
 * `TwoFactorAuthenticatable` - implements `Authenticatable` only, so it can
 * satisfy the rendered stub's `User` type-hint (aliased to `Authenticatable`
 * for this fixture, see IssueTwoFactorChallengeActionStubTest) while still
 * failing the stub's `instanceof TwoFactorAuthenticatable` guard.
 */
final class PlainUser implements Authenticatable
{
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string|null
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
