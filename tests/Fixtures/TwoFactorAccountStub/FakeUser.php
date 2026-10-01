<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

/**
 * The rendered `TwoFactorAuthenticatable` stub with persistence counted in
 * memory instead of written to a database this package does not have.
 * StubLoader must load `Shared/Auth/TwoFactorAuthenticatable.stub` first.
 * `query()` reads rows from `$rows`, so a test can make the locked row differ
 * from the user the request resolved.
 */
final class FakeUser extends TwoFactorAuthenticatable
{
    /**
     * @var array<int|string, FakeUser>
     */
    public static array $rows = [];

    /**
     * @var array<int|string>
     */
    public static array $lockedKeys = [];

    public int $saves = 0;

    public static function query(): FakeUserQuery
    {
        return new FakeUserQuery();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        $this->forceFill($attributes);
        $this->saves++;

        return true;
    }

    public function saveOrFail(array $options = []): bool
    {
        $this->saves++;

        return true;
    }
}
