<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

/**
 * Apps re-date or rename the migrations they get, so a re-run cannot trust the exact
 * filename `StubCopier` would check: any `*_{name}.php` already there counts.
 */
final class MigrationLocator
{
    public function find(string $migrationsDirectory, string $migrationName): ?string
    {
        $matches = glob("{$migrationsDirectory}/*_{$migrationName}.php");

        return $matches === false || $matches === [] ? null : basename($matches[0]);
    }
}
