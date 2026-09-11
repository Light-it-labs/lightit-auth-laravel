<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Installers;

use Illuminate\Console\Command;
use Lightitlabs\Console\LightitConsoleOutput;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

final class ComposerInstaller
{
    use LightitConsoleOutput;

    public function __construct(protected Command $command)
    {
        $this->initializeOutput($this->command);
    }

    /**
     * `composer require`'s exit code can't be trusted on its own: a plugin
     * hooked into `post-update-cmd`/`post-install-cmd` (e.g. a git-hooks
     * installer that fails when there is no `.git` directory) can make the
     * whole command exit non-zero *after* every requested package has
     * already been downloaded, extracted, and added to `vendor/` and
     * `composer.json`. Falling back to `packagesArePresent()` tells the two
     * cases apart: "composer says it failed, but the packages are actually
     * there" (an unrelated plugin/script problem - continue) from "composer
     * says it failed, and the packages really aren't there" (a real
     * failure - propagate it).
     *
     * @param  array<string>  $packages
     */
    public function requirePackages(array $packages): bool
    {
        $command = array_merge(['composer', 'require'], $packages);

        $process = new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']);
        $process->setTimeout(null);

        $this->command->newLine();
        info('📦 Installing composer packages: '.implode(', ', $packages));
        $this->command->line(str_repeat('-', 60));

        $exitCode = $process->run(function ($type, $buffer): void {
            foreach (explode("\n", $buffer) as $line) {
                $line = trim($line);

                if ($line === '') {
                    return;
                }

                if (
                    str_contains($line, 'Installing')
                    || str_contains($line, 'Generating optimized autoload')
                    || str_contains($line, 'Writing lock file')
                    || str_contains($line, 'Package operations')
                    || str_contains($line, 'Nothing to install')
                    || str_contains($line, 'Extracting archive')) {
                    info("   💡 $line");
                }

                if ($type === Process::ERR) {
                    error($line);
                }
            }
        });

        if ($exitCode === 0) {
            return true;
        }

        if (! $this->packagesArePresent($packages)) {
            return false;
        }

        warning(
            'composer require exited with a non-zero status, but '.implode(', ', $packages)
            .' are already present in vendor/ and composer.json. Continuing - this usually means an unrelated '
            .'composer plugin or script failed after the packages were installed.'
        );

        return true;
    }

    /**
     * @param  array<string>  $packages
     */
    private function packagesArePresent(array $packages): bool
    {
        $composerJson = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $require = is_array($composerJson) ? ($composerJson['require'] ?? null) : null;
        $required = is_array($require) ? array_keys($require) : [];

        foreach ($packages as $package) {
            if (! in_array($package, $required, true)) {
                return false;
            }

            if (! is_file(base_path("vendor/{$package}/composer.json"))) {
                return false;
            }
        }

        return true;
    }
}
