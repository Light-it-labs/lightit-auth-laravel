<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\IssueTwoFactorResetTokenActionStub;

/**
 * Stands in for the real PasswordValidatorAction.stub (which hashes against a real
 * User row) so IssueTwoFactorResetTokenAction.stub can be rendered and exercised
 * without Eloquent - see laravel-package-testing: no Eloquent, no database here.
 */
final class PasswordValidatorAction
{
    public string|null $validatedPassword = null;

    public function execute(FakeUser $user, string $password): void
    {
        $this->validatedPassword = $password;
    }
}
