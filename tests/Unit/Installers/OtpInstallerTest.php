<?php

declare(strict_types=1);

use Lightitlabs\Auth\Installers\OtpInstaller;

describe('OtpInstaller migration no-overwrite guard', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/otp-installer-migrations-'.uniqid();
        mkdir($this->directory, 0755, true);

        $reflection = new ReflectionClass(OtpInstaller::class);
        $this->installer = $reflection->newInstanceWithoutConstructor();
        $this->migrationAlreadyExists = $reflection->getMethod('migrationAlreadyExists');
        $this->migrationAlreadyExists->setAccessible(true);
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    });

    it('reports no existing migration in an empty migrations directory', function (): void {
        expect($this->migrationAlreadyExists->invoke($this->installer, $this->directory, 'create_otps_table'))
            ->toBeFalse();
    });

    it('finds a previously copied migration regardless of its timestamp prefix', function (): void {
        touch($this->directory.'/2024_01_01_000000_create_otps_table.php');

        expect($this->migrationAlreadyExists->invoke($this->installer, $this->directory, 'create_otps_table'))
            ->toBeTrue();
    });

    it('ignores a migration for a different table', function (): void {
        touch($this->directory.'/2024_01_01_000000_create_permission_tables.php');

        expect($this->migrationAlreadyExists->invoke($this->installer, $this->directory, 'create_otps_table'))
            ->toBeFalse();
    });
});
