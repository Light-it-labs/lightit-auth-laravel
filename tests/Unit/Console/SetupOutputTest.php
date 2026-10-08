<?php

declare(strict_types=1);

use Lightitlabs\Console\Banner;
use Lightitlabs\Console\ConsoleProfile;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Exceptions\SetupAbortedException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

const LONG_WARNING = 'Lightit\Users\Domain\Models\User does not extend '
    . 'Lightit\Authentication\Domain\TwoFactorAuthenticatable, so every login throws a LogicException.';

const DOCS_URL = 'https://github.com/Light-it-labs/lightit-auth-laravel/blob/main/docs/google-2fa.md';

function runTwoFactorFeature(SetupOutput $setupOutput): bool
{
    return $setupOutput->feature('Two-Factor Authentication', static function () use ($setupOutput): void {
        reportTwoFactorEvents($setupOutput);
    }, DOCS_URL);
}

function reportTwoFactorEvents(SetupOutput $setupOutput): void
{
    $setupOutput->written('config/google2fa.php');
    $setupOutput->written('AUTH-2FA-TODO.md');
    $setupOutput->written('AUTH-2FA-FRONTEND-TODO.md');
    $setupOutput->skipped('routes/api.php');
    $setupOutput->manualStep(
        'Wire the challenge into `LoginAction`',
        'src/Authentication/Domain/Actions/LoginAction.php',
        [
            'public function __construct(',
            '    private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,',
            ') {}',
        ]
    );
    $setupOutput->manualStep('Run `php artisan migrate`');
    $setupOutput->warning(LONG_WARNING);
}

/**
 * @return list<string>
 */
function visibleLines(string $output): array
{
    return explode("\n", rtrim((string) preg_replace('/\e\[[0-9;]*m/', '', $output), "\n"));
}

describe('SetupOutput without a terminal', function (): void {
    beforeEach(function (): void {
        $this->buffer = new BufferedOutput();
        $this->setupOutput = new SetupOutput($this->buffer, new ConsoleProfile(null));
    });

    it('prints one result line per feature instead of a line per file', function (): void {
        runTwoFactorFeature($this->setupOutput);

        expect($this->buffer->fetch())
            ->toBe("✔ Two-Factor Authentication · 3 created · 1 skipped · 1 warning\n");
    });

    it('prints the summary as plain indented text, with no box, colour or wrapping', function (): void {
        runTwoFactorFeature($this->setupOutput);
        $this->setupOutput->summary();

        $output = $this->buffer->fetch();

        expect($output)
            ->not->toMatch('/\e\[/')
            ->not->toContain('│')
            ->toContain("Summary\n  ✔ Two-Factor Authentication\n    Manual steps\n")
            ->toContain(
                "    1. Wire the challenge into LoginAction · src/Authentication/Domain/Actions/LoginAction.php\n"
            )
            ->toContain("    2. Run php artisan migrate\n")
            ->toContain("    Full steps AUTH-2FA-TODO.md (back), AUTH-2FA-FRONTEND-TODO.md (front)\n")
            ->toContain('    Docs       ' . DOCS_URL . "\n")
            ->toContain('    ! ' . LONG_WARNING . "\n")
            ->not->toContain('public function __construct(')
            ->toEndWith("✔ Authentication setup completed!\n");
    });

    it('prints the full instructions of every step only with -v', function (): void {
        $this->buffer->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        runTwoFactorFeature($this->setupOutput);
        $this->setupOutput->summary();

        expect($this->buffer->fetch())
            ->toContain(
                "    1. Wire the challenge into LoginAction · src/Authentication/Domain/Actions/LoginAction.php\n"
            )
            ->toContain("         public function __construct(\n")
            ->toContain(
                "             private readonly IssueTwoFactorChallengeAction \$issueTwoFactorChallengeAction,\n"
            );
    });

    it('lists every written and skipped file only with -v', function (): void {
        $this->buffer->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        runTwoFactorFeature($this->setupOutput);

        expect($this->buffer->fetch())
            ->toContain("    Wrote config/google2fa.php\n")
            ->toContain("    Skipped routes/api.php: the file already exists.\n");
    });

    it(
        'marks a failed feature with a one-line reason, keeps going, and says re-running is safe',
        function (): void {
            $failed = $this->setupOutput->feature('Roles and Permissions', function (): void {
                $this->setupOutput->written('config/permission.php');
                $this->setupOutput->warning('Something to look at.');

                throw new SetupAbortedException('Failed to install spatie/laravel-permission');
            });
            $succeeded = $this->setupOutput->feature('Forgot Password', static fn () => null);

            expect($failed)->toBeFalse()
                ->and($succeeded)->toBeTrue()
                ->and($this->setupOutput->summary())->toBeFalse()
                ->and(visibleLines($this->buffer->fetch()))
                ->toContain('✘ Roles and Permissions · failed')
                ->toContain('  ✘ Roles and Permissions setup failed: Failed to install spatie/laravel-permission')
                ->toContain('    The files it wrote before failing were kept. Re-running is safe.')
                ->toContain('    ! Something to look at.')
                ->toContain('  ✔ Forgot Password')
                ->toContain('✘ Authentication setup did not complete: Roles and Permissions failed.');
        }
    );

    it('still lists the manual steps and checklists of a feature that failed half-way', function (): void {
        $this->setupOutput->feature('Two-Factor Authentication', function (): void {
            reportTwoFactorEvents($this->setupOutput);

            throw new RuntimeException('Unable to write file: src/routes/x.tsx');
        });

        expect($this->setupOutput->summary())->toBeFalse()
            ->and(visibleLines($this->buffer->fetch()))
            ->toContain('  ✘ Two-Factor Authentication setup failed: Unable to write file: src/routes/x.tsx')
            ->toContain(
                '    1. Wire the challenge into LoginAction · src/Authentication/Domain/Actions/LoginAction.php'
            )
            ->toContain('    2. Run php artisan migrate')
            ->toContain('    Full steps AUTH-2FA-TODO.md (back), AUTH-2FA-FRONTEND-TODO.md (front)');
    });

    it('fails a feature that reports an error, but lets it finish', function (): void {
        $succeeded = $this->setupOutput->feature('Two-Factor Authentication', function (): void {
            $this->setupOutput->error('routes/api.php was left inconsistent — inspect it');
            $this->setupOutput->written('AUTH-2FA-TODO.md');
        });

        expect($succeeded)->toBeFalse()
            ->and($this->setupOutput->summary())->toBeFalse()
            ->and(visibleLines($this->buffer->fetch()))
            ->toContain('✘ Two-Factor Authentication · failed')
            ->toContain('  ✘ Two-Factor Authentication')
            ->toContain('    ✘ routes/api.php was left inconsistent — inspect it')
            ->toContain('    Full steps AUTH-2FA-TODO.md (back)')
            ->toContain('✘ Authentication setup did not complete: Two-Factor Authentication failed.');
    });

    it('shows the last 10 lines of an error\'s output, or all of them with -v', function (
        int $verbosity,
        int $firstShown,
        bool $hint,
    ): void {
        $this->buffer->setVerbosity($verbosity);
        $output = array_map(static fn (int $line): string => "composer line {$line}", range(1, 25));

        $this->setupOutput->feature('Roles and Permissions', function () use ($output): void {
            $this->setupOutput->error('composer require spatie/laravel-permission exited with code 2:', $output);
        });
        $this->setupOutput->summary();

        $lines = visibleLines($this->buffer->fetch());

        expect($lines)->toContain('    ✘ composer require spatie/laravel-permission exited with code 2:')
            ->toContain('        composer line 25')
            ->toContain("        composer line {$firstShown}")
            ->not->toContain('        composer line ' . ($firstShown - 1))
            ->and(\in_array('        … 15 earlier lines, run with -v to see them', $lines, true))->toBe($hint);
    })->with([
        'normal' => [OutputInterface::VERBOSITY_NORMAL, 16, true],
        'verbose' => [OutputInterface::VERBOSITY_VERBOSE, 1, false],
    ]);

    it('prints the stack trace of an unexpected failure only with -v', function (int $verbosity, bool $traced): void {
        $this->buffer->setVerbosity($verbosity);

        $this->setupOutput->feature('Forgot Password', static function (): void {
            throw new RuntimeException('Unable to write file: src/Authentication/App/Controllers/X.php');
        });

        expect(str_contains($this->buffer->fetch(), 'Stack trace:'))->toBe($traced);
    })->with([
        'normal' => [OutputInterface::VERBOSITY_NORMAL, false],
        'verbose' => [OutputInterface::VERBOSITY_VERBOSE, true],
    ]);
});

describe('SetupOutput on a terminal', function (): void {
    it('boxes the summary in colour within the terminal width, wrapping instead of losing text', function (): void {
        $buffer = new BufferedOutput(decorated: true);
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(60));

        runTwoFactorFeature($setupOutput);
        $setupOutput->summary();

        $raw = $buffer->fetch();
        $lines = visibleLines($raw);
        $boxed = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '│')));

        expect($raw)->toContain("\e[")
            ->and($lines)->toContain('┌─ Summary ' . str_repeat('─', 48) . '┐')
            ->and($lines)->toContain('└' . str_repeat('─', 58) . '┘');

        foreach ($lines as $line) {
            expect(mb_strwidth($line))->toBeLessThanOrEqual(60);
        }

        foreach ($boxed as $line) {
            expect(mb_strwidth($line))->toBe(60)->and($line)->toEndWith('│');
        }

        $warningStart = (int) array_key_first(array_filter(
            $boxed,
            static fn (string $line): bool => str_starts_with($line, '│   ! '),
        ));
        $warning = implode('', array_map(
            static fn (string $line): string => trim(trim($line, '│'), ' !'),
            array_slice($boxed, $warningStart),
        ));

        expect(str_replace(' ', '', $warning))->toBe(str_replace(' ', '', LONG_WARNING));
    });

    it('keeps one line per file with -v, cutting the start of a long path', function (): void {
        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(40));

        $setupOutput->feature('Two-Factor Authentication', static function () use ($setupOutput): void {
            $setupOutput->written('src/Authentication/Domain/Actions/IssueTwoFactorChallengeAction.php');
        });

        [$status, $detail] = visibleLines($buffer->fetch());

        expect($detail)->toStartWith('    Wrote …')
            ->toEndWith('TwoFactorChallengeAction.php')
            ->and(mb_strwidth($detail))->toBe(40);
    });

    it('prints no ANSI escape sequence at all when NO_COLOR is set, even where colour is supported', function (
        string|false $noColor,
        bool $coloured,
    ): void {
        $previousColorTerm = getenv('COLORTERM');
        putenv('FORCE_COLOR=1');
        $noColor === false ? putenv('NO_COLOR') : putenv("NO_COLOR={$noColor}");

        try {
            $stream = new StreamOutput(fopen('php://memory', 'w+'));
            $setupOutput = new SetupOutput($stream, new ConsoleProfile(80));

            $setupOutput->header('1.4.0', '/home/dev/app', null);
            runTwoFactorFeature($setupOutput);
            $setupOutput->summary();
        } finally {
            putenv('NO_COLOR');
            putenv('FORCE_COLOR');
            $previousColorTerm === false ? putenv('COLORTERM') : putenv("COLORTERM={$previousColorTerm}");
        }

        rewind($stream->getStream());
        $output = (string) stream_get_contents($stream->getStream());

        expect($stream->isDecorated())->toBe($coloured)
            ->and(preg_match('/\e\[/', $output))->toBe($coloured ? 1 : 0)
            ->and(visibleLines($output))->toContain(
                '✔ Two-Factor Authentication · 3 created · 1 skipped · 1 warning'
            )
            ->and(implode("\n", visibleLines($output)))->toContain('┌─ Summary');
    })->with([
        'NO_COLOR set' => ['1', false],
        'NO_COLOR unset (control)' => [false, true],
    ]);

    it('draws the Light-it banner above the title and paths on a wide colour terminal', function (): void {
        $buffer = new BufferedOutput(decorated: true);
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(80));

        $setupOutput->header('1.4.0', '/home/dev/app', '/home/dev/frontend');

        $raw = $buffer->fetch();
        $lines = visibleLines($raw);

        expect($raw)->toContain("\e[")
            ->and($lines)->toContain('  Auth Setup · lightit-auth-laravel 1.4.0')
            ->and($lines)->toContain('  back   /home/dev/app')
            ->and($lines)->toContain('  front  /home/dev/frontend')
            ->and(implode("\n", $lines))->toContain('▄█')
            ->and(\count($lines))->toBeLessThanOrEqual(10);

        foreach ($lines as $line) {
            expect(mb_strwidth($line))->toBeLessThanOrEqual(Banner::WIDTH);
        }
    });

    it('falls back to a one-line banner on a narrow terminal or without colour', function (
        int $width,
        bool $decorated,
    ): void {
        $buffer = new BufferedOutput(decorated: $decorated);
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile($width));

        $setupOutput->header('1.4.0', '/home/dev/app', null);

        expect(visibleLines($buffer->fetch()))->toBe([
            'light-it · Auth Setup · lightit-auth-laravel 1.4.0',
            '  back   /home/dev/app',
            '  front  none found, frontend steps are skipped',
        ]);
    })->with([
        'narrow terminal' => [59, true],
        'no colour' => [80, false],
    ]);

    it('prints the header paths on one line each, keeping the end of a long path', function (): void {
        $buffer = new BufferedOutput();
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(40));

        $setupOutput->header('1.4.0', '/home/dev/projects/acme/backend-application', null);

        expect(visibleLines($buffer->fetch()))->toBe([
            'light-it · Auth Setup · lightit-auth-la…',
            '  back   …jects/acme/backend-application',
            '  front  none found, frontend steps are',
            '         skipped',
        ]);
    });
});

describe('SetupOutput without a terminal header', function (): void {
    it('prints the one-line banner and the paths in full, untouched', function (): void {
        $buffer = new BufferedOutput();
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(null));

        $setupOutput->header('1.4.0', '/home/dev/projects/acme/backend-application', '/home/dev/front');

        expect(visibleLines($buffer->fetch()))->toBe([
            'light-it · Auth Setup · lightit-auth-laravel 1.4.0',
            '  back   /home/dev/projects/acme/backend-application',
            '  front  /home/dev/front',
        ]);
    });
});
