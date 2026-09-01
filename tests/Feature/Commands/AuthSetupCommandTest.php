<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupCommand;
use Lightitlabs\Tests\Fixtures\OrderTrackingAuthSetupCommand;

describe('auth:setup driver resolution', function (): void {
    it('fails naming the missing flag when run without interaction and without a driver', function (): void {
        $this->artisan('auth:setup', ['--no-interaction' => true])
            ->expectsOutputToContain('--driver')
            ->assertFailed();
    });

    it('fails on an unknown driver slug', function (): void {
        $this->artisan('auth:setup', ['--driver' => ['bogus-driver'], '--no-interaction' => true])
            ->expectsOutputToContain('Unknown authentication driver')
            ->assertFailed();
    });

    it('fails when both sanctum drivers are requested in one comma separated value', function (): void {
        $this->artisan('auth:setup', ['--driver' => ['sanctum-token,sanctum-cookie'], '--no-interaction' => true])
            ->expectsOutputToContain('You cannot select both')
            ->assertFailed();
    });
});

describe('auth:setup exit codes', function (): void {
    it('succeeds and prints the reproducible invocation in canonical slugs', function (): void {
        Artisan::registerCommand(new FakeAuthSetupCommand());

        $this->artisan('auth:setup-fake', [
            '--driver' => [' Sanctum-Cookie '],
            '--frontend-path' => '../react-template',
        ])
            ->expectsConfirmation('Would you like to enable Two-Factor Authentication?', 'no')
            ->expectsConfirmation('Would you like to enable Roles and Permissions?', 'no')
            ->expectsConfirmation('Would you like to enable the Forgot Password flow?', 'no')
            ->expectsOutputToContain(
                'php artisan auth:setup --driver=sanctum-cookie --frontend-path=../react-template -n'
            )
            ->assertSuccessful();
    });

    it('fails when an installer throws', function (): void {
        Artisan::registerCommand(new FakeAuthSetupCommand(installerThrows: true));

        $this->artisan('auth:setup-fake', ['--driver' => ['sanctum-cookie']])
            ->expectsConfirmation('Would you like to enable Two-Factor Authentication?', 'no')
            ->expectsConfirmation('Would you like to enable Roles and Permissions?', 'no')
            ->expectsConfirmation('Would you like to enable the Forgot Password flow?', 'no')
            ->expectsOutputToContain('the installer exploded')
            ->assertFailed();
    });
});

describe('auth:setup driver/2FA ordering', function (): void {
    it('sets up drivers before 2FA, since Google2FAInstaller deliberately overwrites SanctumCookie controllers', function (): void {
        OrderTrackingAuthSetupCommand::$callOrder = [];

        Artisan::registerCommand(new OrderTrackingAuthSetupCommand());

        $this->artisan('auth:setup-order-tracking', ['--driver' => ['sanctum-cookie'], '--skip-frontend' => true])
            ->expectsConfirmation('Would you like to enable Two-Factor Authentication?', 'yes')
            ->expectsConfirmation('Would you like to enable Roles and Permissions?', 'no')
            ->expectsConfirmation('Would you like to enable the Forgot Password flow?', 'no')
            ->assertSuccessful();

        expect(OrderTrackingAuthSetupCommand::$callOrder)->toBe(['setupDrivers', 'setup2FA']);
    });
});
