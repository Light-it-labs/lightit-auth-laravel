<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAuthenticatableStub;

/**
 * A User shaped like the boilerplate's, with its own casts() method, extending the
 * rendered `TwoFactorAuthenticatable` stub. TwoFactorAuthenticatableRecoveryCodesStubTest
 * renders that stub into this namespace before this class is autoloaded.
 */
class ConsumerUser extends TwoFactorAuthenticatable
{
    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
        ];
    }
}
