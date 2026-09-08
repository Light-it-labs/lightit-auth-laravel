<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Enums\LoginMethod;
use RuntimeException;

final class FakeAuthSetupCommand extends AuthSetupCommand
{
    protected $signature = 'auth:setup-fake';

    /**
     * @var list<string>
     */
    public array $invokedFeatures = [];

    /**
     * @param  list<LoginMethod>  $loginMethods
     * @param  list<Feature>  $features
     */
    public function __construct(
        private readonly bool $installerThrows = false,
        private readonly array $loginMethods = [LoginMethod::Password],
        private readonly array $features = [],
    ) {
        parent::__construct();
    }

    /**
     * @return list<LoginMethod>
     */
    protected function resolveLoginMethods(): array
    {
        return $this->loginMethods;
    }

    /**
     * @return list<Feature>
     */
    protected function resolveFeatures(): array
    {
        return $this->features;
    }

    protected function setupLoginMethods(array $methods): void
    {
        if ($this->installerThrows) {
            throw new RuntimeException('the installer exploded');
        }
    }

    protected function setup2FA(): void
    {
        $this->invokedFeatures[] = Feature::TwoFactorAuthentication->value;
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->invokedFeatures[] = Feature::RolesAndPermissions->value;
    }

    protected function setupOtp(): void
    {
        $this->invokedFeatures[] = Feature::Otp->value;
    }

    protected function setupForgotPassword(): void
    {
        $this->invokedFeatures[] = Feature::ForgotPassword->value;
    }
}
