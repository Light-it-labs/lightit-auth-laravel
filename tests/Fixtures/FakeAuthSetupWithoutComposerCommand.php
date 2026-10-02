<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;

/**
 * The real `auth:setup` flow, prompt included, with the 2FA step reduced to its frontend
 * installer, so a test can make that installer fail mid-way without the composer-heavy
 * backend installer running first.
 */
final class FakeAuthSetupWithoutComposerCommand extends AuthSetupCommand
{
    protected $signature = 'auth-setup-without-composer-fake {--frontend-path= : Path to the React project}';

    protected function setup2FA(): void
    {
        $this->setup2FAFrontend();
    }
}
