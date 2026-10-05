<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\SocialLoginStub;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Stands in for the shared `LoginByUserAction`: records who went through the 2FA gate (and
 * at which transaction level), and answers with a challenge for the emails set up with 2FA.
 */
final class LoginByUserAction
{
    /** @var list<string> */
    public array $gatedEmails = [];

    /** @var list<int> */
    public array $transactionLevels = [];

    /**
     * @param list<string> $twoFactorEmails
     */
    public function __construct(private readonly array $twoFactorEmails = [])
    {
    }

    public function execute(User $user): void
    {
        $this->gatedEmails[] = $user->email;
        $this->transactionLevels[] = DB::transactionLevel();

        if (\in_array($user->email, $this->twoFactorEmails, true)) {
            throw new TwoFactorChallengeException('Two-factor authentication required.');
        }
    }

    public function executeAfterChallenge(): void
    {
        throw new LogicException('A social sign-in must go through the 2FA gate.');
    }
}
