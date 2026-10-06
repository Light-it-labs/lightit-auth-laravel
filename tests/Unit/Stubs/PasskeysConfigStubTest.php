<?php

declare(strict_types=1);

use Lightitlabs\Auth\Installers\PasskeysInstaller;

/**
 * @return array<string, mixed>
 */
function passkeysConfigIn(string $appEnv): array
{
    $previous = $_SERVER['APP_ENV'] ?? null;
    $_SERVER['APP_ENV'] = $appEnv;

    try {
        return require PasskeysInstaller::stubDirectory() . '/config/passkeys.stub';
    } finally {
        if ($previous === null) {
            unset($_SERVER['APP_ENV']);
        } else {
            $_SERVER['APP_ENV'] = $previous;
        }
    }
}

describe('config/passkeys.php stub', function (): void {
    it('falls back to the local relying party in local and testing', function (string $appEnv): void {
        $config = passkeysConfigIn($appEnv);

        expect($config['relying_party']['id'])->toBe('localhost')
            ->and($config['allowed_origins'])->toBe(['http://localhost:5173']);
    })->with(['local', 'testing']);

    it('leaves the relying party unset anywhere else', function (string $appEnv): void {
        $config = passkeysConfigIn($appEnv);

        expect($config['relying_party']['id'])->toBeNull()
            ->and($config['allowed_origins'])->toBe([]);
    })->with(['production', 'staging']);
});
