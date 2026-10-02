<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub;

use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/**
 * A software ES256 authenticator: produces the `none`-attestation credential a
 * browser posts after navigator.credentials.create(), with every field the
 * relying party checks (challenge, origin, RP ID hash, UV flag) settable.
 */
final class FakeAuthenticator
{
    private const FLAG_USER_PRESENT = 0x01;

    private const FLAG_USER_VERIFIED = 0x04;

    private const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;

    public readonly string $credentialId;

    private readonly OpenSSLAsymmetricKey $privateKey;

    public function __construct()
    {
        $this->credentialId = random_bytes(32);
        $this->privateKey = openssl_pkey_new([
            'private_key_type' => \OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]) ?: throw new RuntimeException('Could not generate a P-256 key.');
    }

    /**
     * @param array<string, mixed> $creationOptions the decoded JSON the server handed the browser
     */
    public function attestation(
        array $creationOptions,
        string $origin,
        string|null $challenge = null,
        string|null $relyingPartyId = null,
        bool $userVerified = true,
    ): string {
        $clientDataJson = (string) json_encode([
            'type' => 'webauthn.create',
            'challenge' => $challenge ?? $creationOptions['challenge'],
            'origin' => $origin,
            'crossOrigin' => false,
        ], \JSON_UNESCAPED_SLASHES);

        $flags = self::FLAG_USER_PRESENT|self::FLAG_ATTESTED_CREDENTIAL_DATA
            |($userVerified ? self::FLAG_USER_VERIFIED : 0);

        $authenticatorData = hash('sha256', $relyingPartyId ?? $creationOptions['rp']['id'], true)
            . \chr($flags)
            . pack('N', 0)
            . str_repeat("\0", 16)
            . pack('n', \strlen($this->credentialId))
            . $this->credentialId
            . $this->cosePublicKey();

        $attestationObject = Cbor::encode([
            'fmt' => 'none',
            'attStmt' => [],
            'authData' => new CborBytes($authenticatorData),
        ]);

        return (string) json_encode([
            'id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataJson),
                'attestationObject' => Base64UrlSafe::encodeUnpadded($attestationObject),
                'transports' => ['internal'],
            ],
        ]);
    }

    private function cosePublicKey(): string
    {
        $details = openssl_pkey_get_details($this->privateKey);

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Could not read the P-256 public key.');
        }

        return Cbor::encode([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => new CborBytes(str_pad($details['ec']['x'], 32, "\0", \STR_PAD_LEFT)),
            -3 => new CborBytes(str_pad($details['ec']['y'], 32, "\0", \STR_PAD_LEFT)),
        ]);
    }
}
