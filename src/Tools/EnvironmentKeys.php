<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

final class EnvironmentKeys
{
    /**
     * A commented line is documentation, not configuration. Treating it as a set key
     * makes the installer skip the real variable and leave the app misconfigured.
     */
    public function isSet(string $contents, string $key): bool
    {
        return preg_match('/^[ \t]*'.preg_quote($key, '/').'[ \t]*=/m', $contents) === 1;
    }

    /**
     * @return array{key: string, value: string}|null
     */
    public function parse(string $line): ?array
    {
        $matches = [];

        if (preg_match('/^([A-Z][A-Z\d_]*)=(.*)$/', trim($line), $matches) !== 1) {
            return null;
        }

        return ['key' => $matches[1], 'value' => $matches[2]];
    }
}
