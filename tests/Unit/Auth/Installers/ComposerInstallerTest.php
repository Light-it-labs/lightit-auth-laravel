<?php

declare(strict_types=1);

use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Console\ConsoleProfile;
use Lightitlabs\Console\SetupOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A stand-in for composer that writes 25 numbered lines to stderr and exits 2.
 *
 * @return list<string>
 */
function failingComposer(): array
{
    return [
        PHP_BINARY,
        '-r',
        'for ($i = 1; $i <= 25; $i++) { fwrite(STDERR, "composer says {$i}\n"); } exit(2);',
        '--',
    ];
}

describe('ComposerInstaller', function (): void {
    it('reports success without an error when composer exits 0', function (): void {
        $buffer = new BufferedOutput();
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(null));
        $installer = new ComposerInstaller($setupOutput, [PHP_BINARY, '-r', 'exit(0);', '--']);

        $succeeded = $setupOutput->feature('Roles and Permissions', static function () use ($installer): void {
            expect($installer->requirePackages(['spatie/laravel-permission']))->toBeTrue();
        });

        expect($succeeded)->toBeTrue();
    });

    it('fails the feature with the end of composer\'s output, or all of it with -v', function (
        int $verbosity,
        int $firstShown,
        bool $hint,
    ): void {
        $buffer = new BufferedOutput($verbosity);
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(null));
        $installer = new ComposerInstaller($setupOutput, failingComposer());

        $succeeded = $setupOutput->feature('Roles and Permissions', static function () use ($installer): void {
            expect($installer->requirePackages(['spatie/laravel-permission']))->toBeFalse();
        });
        $setupOutput->summary();

        $lines = explode("\n", $buffer->fetch());

        expect($succeeded)->toBeFalse()
            ->and($lines)->toContain('    ✘ composer require spatie/laravel-permission exited with code 2:')
            ->toContain('        composer says 25')
            ->toContain("        composer says {$firstShown}")
            ->not->toContain('        composer says ' . ($firstShown - 1))
            ->and(in_array('        … 15 earlier lines, run with -v to see them', $lines, true))->toBe($hint);
    })->with([
        'normal' => [OutputInterface::VERBOSITY_NORMAL, 16, true],
        'verbose' => [OutputInterface::VERBOSITY_VERBOSE, 1, false],
    ]);
});
