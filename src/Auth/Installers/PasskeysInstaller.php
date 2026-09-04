<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Contracts\AuthInstallerInterface;
use Lightitlabs\Tools\StubCopier;

final class PasskeysInstaller implements AuthInstallerInterface
{
    public function __construct(
        private readonly Command $command,
        private readonly ComposerInstaller $composerInstaller,
        private readonly StubCopier $stubCopier,
    ) {}

    public function install(): void
    {
        if (! $this->composerInstaller->requirePackages(['web-auth/webauthn-lib:^5.3'])) {
            $this->command->error('Failed to install web-auth/webauthn-lib');

            return;
        }

        $this->copyConfigFile();
        $this->copyMigration();

        $this->composerInstaller->printSuccess('Passkeys (WebAuthn) installed successfully!');
    }

    private function copyConfigFile(): void
    {
        $this->composerInstaller->printStep(1, 2, 'Copying config file');

        if (! is_dir(config_path())) {
            mkdir(config_path(), 0755, true);
        }

        $this->stubCopier->copy(
            __DIR__.'/../../Stubs/Passkeys/config/passkeys.stub',
            config_path('passkeys.php')
        );
        $this->composerInstaller->printConfigPublished('Config file published: config/passkeys.php');
    }

    private function copyMigration(): void
    {
        $this->composerInstaller->printStep(2, 2, 'Copying migration files');

        if (glob(base_path('database/migrations/*_create_passkeys_table.php')) !== []) {
            $this->composerInstaller->printMigrationCreated(
                'Skipped: a create_passkeys_table migration already exists.'
            );

            return;
        }

        $stub = __DIR__.'/../../Stubs/Passkeys/database/migrations/create_passkeys_table.stub';
        $timestamp = date('Y_m_d_His');
        $destination = "database/migrations/{$timestamp}_create_passkeys_table.php";

        $this->stubCopier->copy(
            $stub,
            base_path($destination)
        );
        $this->composerInstaller->printMigrationCreated("Created: {$destination}");
    }
}
