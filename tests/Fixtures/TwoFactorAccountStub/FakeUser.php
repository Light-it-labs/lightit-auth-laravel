<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

/**
 * The rendered `TwoFactorAuthenticatable` stub with persistence counted in
 * memory instead of written to a database this package does not have.
 * StubLoader must load `Shared/Auth/TwoFactorAuthenticatable.stub` first.
 */
final class FakeUser extends TwoFactorAuthenticatable
{
    public int $saves = 0;

    public function saveOrFail(array $options = []): bool
    {
        $this->saves++;

        return true;
    }
}
