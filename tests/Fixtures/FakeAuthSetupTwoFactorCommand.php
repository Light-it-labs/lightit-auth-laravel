<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;

/**
 * Drives the real `--frontend-path` option parsing straight into
 * {@see AuthSetupCommand::setup2FAFrontend()}, so tests can exercise the wiring without
 * faking the interactive multiselect prompt or running the composer-heavy 2FA backend
 * installer that {@see AuthSetupCommand::setup2FA()} runs first.
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
