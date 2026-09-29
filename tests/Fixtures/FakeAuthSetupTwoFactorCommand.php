<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;
use Lightitlabs\Enums\Feature;

/**
 * Two-factor authentication is withheld from {@see Feature::selectable()},
 * so `auth:setup` never reaches {@see AuthSetupCommand::setup2FAFrontend()} through the
 * interactive multiselect prompt. This fixture drives the real `--frontend-path` option
 * parsing straight into it, so tests can exercise the wiring without faking the prompt or
 * running the composer-heavy 2FA backend installer.
 */
final class FakeAuthSetupTwoFactorCommand extends AuthSetupCommand
{
    protected $signature = 'auth-setup-two-factor-fake {--frontend-path= : Path to the React project}';

    public function handle(): int
    {
        $this->setup2FAFrontend();

        return self::SUCCESS;
    }
}
