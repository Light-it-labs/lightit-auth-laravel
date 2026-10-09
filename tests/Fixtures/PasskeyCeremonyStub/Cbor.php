<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub;

use InvalidArgumentException;

/**
 * The subset of CBOR (RFC 8949) an attestation object and a COSE key need:
 * integers, byte strings, text strings and maps.
 */
final class Cbor
{
    private const MAJOR_UNSIGNED = 0;

    private const MAJOR_NEGATIVE = 1;

    private const MAJOR_BYTES = 2;

    private const MAJOR_TEXT = 3;

    private const MAJOR_MAP = 5;

    public static function encode(mixed $value): string
    {
        return match (true) {
            \is_int($value) && $value >= 0 => self::head(self::MAJOR_UNSIGNED, $value),
            \is_int($value) => self::head(self::MAJOR_NEGATIVE, -1 - $value),
            $value instanceof CborBytes => self::head(self::MAJOR_BYTES, \strlen($value->bytes)) . $value->bytes,
            \is_string($value) => self::head(self::MAJOR_TEXT, \strlen($value)) . $value,
            \is_array($value) => self::map($value),
            default => throw new InvalidArgumentException('Unsupported CBOR value.'),
        };
    }

    /**
     * @param array<array-key, mixed> $entries
     */
    private static function map(array $entries): string
    {
        $encoded = self::head(self::MAJOR_MAP, \count($entries));

        foreach ($entries as $key => $value) {
            $encoded .= self::encode($key) . self::encode($value);
        }

        return $encoded;
    }

    private static function head(int $major, int $length): string
    {
        return match (true) {
            $length < 24 => \chr(($major << 5)|$length),
            $length < 0x100 => \chr(($major << 5)|24) . \chr($length),
            $length < 0x10000 => \chr(($major << 5)|25) . pack('n', $length),
            default => \chr(($major << 5)|26) . pack('N', $length),
        };
    }
}
