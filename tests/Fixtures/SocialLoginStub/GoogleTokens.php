<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\SocialLoginStub;

use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Signs Google-shaped ID tokens with a throwaway RSA key and publishes that key as the JWKS
 * Google serves, so the token check runs offline against a key the test controls.
 */
final class GoogleTokens
{
    public const CLIENT_ID = 'demo-client.apps.googleusercontent.com';

    public const KEY_ID = 'test-key';

    private readonly OpenSSLAsymmetricKey $privateKey;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('Could not generate a test RSA key.');
        }

        $this->privateKey = $key;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function sign(array $overrides = [], string $keyId = self::KEY_ID): string
    {
        $claims = array_filter([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => '100000000000000000001',
            'email' => 'demo@example.com',
            'email_verified' => true,
            'name' => 'Demo User',
            'iat' => time() - 10,
            'exp' => time() + 3600,
            ...$overrides,
        ], static fn (mixed $value): bool => $value !== null);

        return JWT::encode($claims, $this->privateKey, 'RS256', $keyId);
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details($this->privateKey);

        if ($details === false) {
            throw new RuntimeException('Could not read the test RSA key.');
        }

        return [
            'keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => self::KEY_ID,
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ]],
        ];
    }
}
