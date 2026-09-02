<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Enums\Feature;
use Lightitlabs\Tests\Fixtures\FakeAuthSetupCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;

describe('auth:setup removed --driver flag', function (): void {
    it('fails legibly rather than silently when the removed --driver flag is passed', function (): void {
        expect(fn () => $this->artisan('auth:setup', ['--driver' => 'sanctum-token']))
            ->toThrow(InvalidOptionException::class, 'The "--driver" option does not exist.');
    });
});

describe('auth:setup exit codes', function (): void {
    it('succeeds with the default login method and no optional features', function (): void {
        Artisan::registerCommand(new FakeAuthSetupCommand);

        $this->artisan('auth:setup-fake')->assertSuccessful();
    });

    it('fails when an installer throws', function (): void {
        Artisan::registerCommand(new FakeAuthSetupCommand(installerThrows: true));

        $this->artisan('auth:setup-fake')
            ->expectsOutputToContain('the installer exploded')
            ->assertFailed();
    });
});

describe('auth:setup feature wiring', function (): void {
    it('wires every Feature case to a handler', function (): void {
        Artisan::registerCommand($fake = new FakeAuthSetupCommand(features: Feature::cases()));

        $this->artisan('auth:setup-fake')->assertSuccessful();

        expect($fake->invokedFeatures)->toEqualCanonicalizing(
            array_map(static fn (Feature $feature): string => $feature->value, Feature::cases())
        );
    });
});
