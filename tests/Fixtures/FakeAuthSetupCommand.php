<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;
use RuntimeException;

final class FakeAuthSetupCommand extends AuthSetupCommand
{
    protected $name = 'auth:setup-fake';

    public function __construct(private readonly bool $installerThrows = false)
    {
        parent::__construct();
    }

    protected function setupDrivers(array $drivers): void
    {
        if ($this->installerThrows) {
            throw new RuntimeException('the installer exploded');
        }
    }
}
