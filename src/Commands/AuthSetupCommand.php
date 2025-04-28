<?php

declare(strict_types=1);

namespace Lightit\Commands;

use Illuminate\Console\Command;
use Lightit\Auth\Installers\ComposerInstaller;
use Lightit\Auth\Installers\Google2FAInstaller;
use Lightit\Auth\Installers\GoogleSSOInstaller;
use Lightit\Auth\Installers\JwtInstaller;
use Lightit\Auth\Installers\LaravelPermissionInstaller;
use Lightit\Auth\Installers\SanctumInstaller;
use Lightit\Console\LightitConsoleOutput;
use Lightit\Enums\AuthDriver;
use Lightit\Tools\FileManipulator;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\multiselect;

class AuthSetupCommand extends Command
{
    use LightitConsoleOutput;

    public function __construct()
    {
        parent::__construct();
        $this->initializeOutput($this);
    }

    protected $signature = 'auth:setup';

    protected $description = 'Setup the authentication structure';

    public function handle(): int
    {
        $this->output->writeln('');
        $this->output->writeln("\e[0;31m     _         _   _       ____            _                     \e[0m");
        $this->output->writeln("\e[0;31m    / \  _   _| |_| |__   |  _ \ __ _  ___| | ____ _  __ _  ___  \e[0m");
        $this->output->writeln("\e[0;31m   / _ \| | | | __| '_ \  | |_) / _` |/ __| |/ / _` |/ _` |/ _ \ \e[0m");
        $this->output->writeln("\e[0;31m  / ___ \ |_| | |_| | | | |  __/ (_| | (__|   < (_| | (_| |  __/ \e[0m");
        $this->output->writeln("\e[0;31m /_/   \_\__,_|\__|_| |_| |_|   \__,_|\___|_|\_\__,_|\__, |\___|  \e[0m");
        $this->output->writeln("\e[0;31m                                                     |___/       \e[0m");
        $this->output->writeln('');
        $this->output->writeln("\e[0;35mLightit's package to streamline authentication, authorization,\e[0m");
        $this->output->writeln("\e[0;35mroles, and permissions setup in Laravel boilerplates.\e[0m");
        $this->output->writeln('');

        $driversValues = array_map(
            fn ($v) => $v->value,
            AuthDriver::cases()
        );

        do {
            $drivers = multiselect(
                label: 'Select authentication drivers',
                options: $driversValues,
                required: true,
                hint: 'Press [space] to select, [enter] to confirm.'
            );

            if (in_array(AuthDriver::Jwt->value, $drivers) && in_array(AuthDriver::Sanctum->value, $drivers)) {
                error('You cannot select both JWT and Sanctum authentication drivers.');
                $drivers = null;
            }
        } while (empty($drivers));

        $enable2FA = confirm(
            label: 'Would you like to enable Two-Factor Authentication?',
            default: false,
        );

        $enableRolesAndPermissions = confirm(
            label: 'Would you like to enable Roles and Permissions?',
            default: false,
        );

        /** @var array<string> $drivers */
        $this->setupDrivers($drivers);

        if ($enable2FA) {
            $this->setup2FA();
        }

        if ($enableRolesAndPermissions) {
            $this->setupRolesAndPermissions();
        }

        $this->printSuccess('Authentication setup completed!');

        return self::SUCCESS;
    }

    /**
     * @param array<string> $drivers
     */
    protected function setupDrivers(array $drivers): void
    {
        $setup = [
            AuthDriver::Jwt->value => fn () => $this->setupJWT(),
            AuthDriver::Sanctum->value => fn () => $this->setupSanctum(),
            AuthDriver::GoogleSso->value => fn () => $this->setupGoogleSSO(),
        ];

        foreach ($drivers as $driver) {
            $key = AuthDriver::from($driver)->value;
            $setup[$key]();
        }
    }

    protected function setupJWT(): void
    {
        $this->printBoxedMessage('🛠 Setting up JWT...');

        $composerInstaller = new ComposerInstaller($this);
        $fileManipulator = new FileManipulator($this);
        $jwtInstaller = new JwtInstaller($this, $composerInstaller, $fileManipulator);
        $jwtInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupSanctum(): void
    {
        $this->printBoxedMessage('🛠 Setting up Sanctum...');

        $composerInstaller = new ComposerInstaller($this);
        $sanctumInstaller = new SanctumInstaller($this, $composerInstaller);
        $sanctumInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupGoogleSSO(): void
    {
        $this->printBoxedMessage('🛠 Setting up Google SSO...');

        $composerInstaller = new ComposerInstaller($this);
        $jwtInstaller = new GoogleSSOInstaller($this, $composerInstaller);
        $jwtInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setup2FA(): void
    {
        $this->printBoxedMessage('🛠 Setting up 2FA...');

        $composerInstaller = new ComposerInstaller($this);
        $google2FAInstaller = new Google2FAInstaller($this, $composerInstaller);
        $google2FAInstaller->install();
        $this->printSectionSeparator();
    }

    protected function setupRolesAndPermissions(): void
    {
        $this->printBoxedMessage('🛠 Setting up Roles and Permissions...');

        $composerInstaller = new ComposerInstaller($this);
        $laravelPermission = new LaravelPermissionInstaller($this, $composerInstaller);
        $laravelPermission->install();
        $this->printSectionSeparator();
    }
}
