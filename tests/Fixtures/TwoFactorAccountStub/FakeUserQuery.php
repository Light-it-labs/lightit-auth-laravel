<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The `User::query()->whereKey()->lockForUpdate()->firstOrFail()` chain the
 * stubs use, answered from `FakeUser::$rows`.
 */
final class FakeUserQuery
{
    private int|string|null $key = null;

    public function whereKey(int|string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function lockForUpdate(): self
    {
        if ($this->key !== null) {
            FakeUser::$lockedKeys[] = $this->key;
        }

        return $this;
    }

    public function firstOrFail(): FakeUser
    {
        if ($this->key === null || ! isset(FakeUser::$rows[$this->key])) {
            throw new ModelNotFoundException();
        }

        return FakeUser::$rows[$this->key];
    }
}
