<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;

/**
 * Stands in for `Lightit\Users\Domain\Models\User` when
 * VerifyTwoFactorToken.stub is rendered into this test namespace - only
 * `query()->find($id)` is exercised, so this is a static in-memory registry
 * rather than a real Eloquent model (this package has no database).
 */
final class FakeUser
{
    /** @var array<string, self> */
    public static array $registry = [];

    public function __construct(public readonly string $id)
    {
    }

    public static function query(): FakeUserQuery
    {
        return new FakeUserQuery();
    }
}
