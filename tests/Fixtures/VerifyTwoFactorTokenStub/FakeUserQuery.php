<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\VerifyTwoFactorTokenStub;

final class FakeUserQuery
{
    public function find(string $id): FakeUser|null
    {
        return FakeUser::$registry[$id] ?? null;
    }
}
