<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;
use Lightitlabs\Enums\AuthDriver;

/**
 * Records the order setupDrivers()/setup2FA() are invoked in, without running the real
 * installers, so tests can pin AuthSetupCommand::handle()'s ordering contract:
 * Google2FAInstaller deliberately overwrites files SanctumCookieInstaller wrote, so
 * setupDrivers() must run first.
 */
final class OrderTrackingAuthSetupCommand extends AuthSetupCommand
{
    protected $name = 'auth:setup-order-tracking';

    /** @var list<string> */
    public static array $callOrder = [];

    /**
     * @param  list<AuthDriver>  $drivers
     */
    protected function setupDrivers(array $drivers): void
    {
        self::$callOrder[] = 'setupDrivers';
    }

    /**
     * @param  list<AuthDriver>  $drivers
     */
    protected function setup2FA(array $drivers): void
    {
        self::$callOrder[] = 'setup2FA';
    }
}
