<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Tests\Fixtures\PasskeyChallengeStoreStub\PasskeyChallengeStore;

$tempFile = sys_get_temp_dir() . '/passkey-challenge-store-stub.php';
file_put_contents($tempFile, str_replace(
    ['namespace Lightit\Authentication\Domain;', 'use Lightit\Users\Domain\Models\User;'],
    ['namespace Lightitlabs\Tests\Fixtures\PasskeyChallengeStoreStub;', 'use Illuminate\Foundation\Auth\User;'],
    (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/PasskeyChallengeStore.stub'),
));
require_once $tempFile;

function passkeyUser(int $id): User
{
    return (new User())->forceFill(['id' => $id]);
}

describe('PasskeyChallengeStore stub', function (): void {
    beforeEach(function (): void {
        Config::set('passkeys.challenge_ttl_seconds', 300);
        $this->store = new PasskeyChallengeStore();
    });

    it('hands the registration options back once, to the same user only', function (): void {
        $this->store->putRegistration(passkeyUser(7), '{"challenge":"abc"}');

        expect($this->store->pullRegistration(passkeyUser(8)))->toBeNull()
            ->and($this->store->pullRegistration(passkeyUser(7)))->toBe('{"challenge":"abc"}')
            ->and($this->store->pullRegistration(passkeyUser(7)))->toBeNull();
    });

    it('refuses the options to a request that loses the race for the lock', function (): void {
        $this->store->putRegistration(passkeyUser(7), '{"challenge":"abc"}');

        $lock = Cache::lock('passkeys:registration:7:lock', 10);
        $lock->get();

        expect($this->store->pullRegistration(passkeyUser(7)))->toBeNull();

        $lock->release();

        expect($this->store->pullRegistration(passkeyUser(7)))->toBe('{"challenge":"abc"}');
    });

    it('drops the options after the configured TTL', function (): void {
        Config::set('passkeys.challenge_ttl_seconds', 60);
        $this->store->putRegistration(passkeyUser(7), '{"challenge":"abc"}');

        $this->travel(61)->seconds();

        expect($this->store->pullRegistration(passkeyUser(7)))->toBeNull();
    });
});
