<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures;

use Lightitlabs\Commands\AuthSetupCommand;
use Lightitlabs\Enums\Feature;
use RuntimeException;

final class FakeAuthSetupCommand extends AuthSetupCommand
{
    protected $signature = 'auth:setup-fake';

    /**
     * @var list<string>
     */
    public array $invokedFeatures = [];

    /**
     * @param list<Feature> $features
     */
    public function __construct(
        private readonly bool $installerThrows = false,
        private readonly array $features = [],
    ) {
        parent::__construct();
    }

    /**
     * @return list<Feature>
     */
    protected function resolveFeatures(): array
    {
        return $this->features;
    }

    /**
     * @param array<Feature> $features
     */
    protected function setupFeatures(array $features): void
    {
        if ($this->installerThrows) {
            throw new RuntimeException('the installer exploded');
        }

        parent::setupFeatures($features);
    }

    protected function setupGoogleSSO(): void
    {
        $this->invokedFeatures[] = Feature::GoogleSso->value;
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
