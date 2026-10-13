<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Tools\MigrationLocator;

describe('MigrationLocator', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir() . '/migration-locator-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->directory);
    });

    it('finds a migration with the same name under any timestamp', function (): void {
        touch($this->directory . '/2031_01_01_000000_create_passkeys_table.php');

        expect(new MigrationLocator()->find($this->directory, 'create_passkeys_table'))
            ->toBe('2031_01_01_000000_create_passkeys_table.php');
    });

    it('ignores migrations for another table or that only share part of the name', function (string $file): void {
        touch($this->directory . '/' . $file);

        expect(new MigrationLocator()->find($this->directory, 'create_passkeys_table'))->toBeNull();
    })->with([
        '2031_01_01_000000_create_permission_tables.php',
        '2031_01_01_000000_create_passkeys_table_backup.php',
        '2031_01_01_000000_create_passkeys_table.php.bak',
    ]);

    it('returns null for an empty or missing directory', function (): void {
        expect(new MigrationLocator()->find($this->directory, 'create_passkeys_table'))->toBeNull()
            ->and(new MigrationLocator()->find($this->directory . '/missing', 'create_passkeys_table'))->toBeNull();
    });
});
