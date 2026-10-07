<?php

declare(strict_types=1);

use Lightitlabs\Console\ConsoleProfile;
use Lightitlabs\Console\SetupOutput;
use Lightitlabs\Exceptions\SetupAbortedException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

const LONG_WARNING = 'Lightit\Users\Domain\Models\User does not extend '
    . 'Lightit\Authentication\Domain\TwoFactorAuthenticatable, so every login throws a LogicException.';

function runTwoFactorFeature(SetupOutput $setupOutput): bool
{
    return $setupOutput->feature('Two-Factor Authentication', static function () use ($setupOutput): void {
        $setupOutput->written('config/google2fa.php');
        $setupOutput->written('AUTH-2FA-TODO.md');
        $setupOutput->skipped('routes/api.php');
        $setupOutput->manualStep('Inject the challenge action into LoginAction via its constructor:', [
            'public function __construct(',
            '    private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,',
            ') {}',
        ]);
        $setupOutput->manualStep('Then run php artisan migrate.');
        $setupOutput->warning(LONG_WARNING);
    }, 'vendor/light-it-labs/lightit-auth-laravel/docs/google-2fa.md');
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
            ->toBe("✔ Two-Factor Authentication · 2 created · 1 skipped · 1 warning\n");
    });

    it('prints the summary as plain indented text, with no box, colour or wrapping', function (): void {
        runTwoFactorFeature($this->setupOutput);
        $this->setupOutput->summary();

        $output = $this->buffer->fetch();

        expect($output)
            ->not->toContain("\e[")
            ->not->toContain('│')
            ->toContain("Summary\n  ✔ Two-Factor Authentication\n    Manual steps\n")
            ->toContain("    1. Inject the challenge action into LoginAction via its constructor:\n")
            ->toContain("         public function __construct(\n")
            ->toContain(
                "             private readonly IssueTwoFactorChallengeAction \$issueTwoFactorChallengeAction,\n"
            )
            ->toContain("    2. Then run php artisan migrate.\n")
            ->toContain("    Checklist AUTH-2FA-TODO.md\n")
            ->toContain("    Docs      vendor/light-it-labs/lightit-auth-laravel/docs/google-2fa.md\n")
            ->toContain('    ! ' . LONG_WARNING . "\n")
            ->toEndWith("✔ Authentication setup completed!\n");
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
                $this->setupOutput->warning('composer require spatie/laravel-permission exited with code 1.');

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
                ->toContain('    ! composer require spatie/laravel-permission exited with code 1.')
                ->toContain('  ✔ Forgot Password')
                ->toContain('✘ Authentication setup did not complete: Roles and Permissions failed.');
        }
    );

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

    it('draws the box without colour when NO_COLOR is set', function (): void {
        putenv('NO_COLOR=1');

        try {
            $stream = new StreamOutput(fopen('php://memory', 'w+'));
            $setupOutput = new SetupOutput($stream, new ConsoleProfile(80));

            runTwoFactorFeature($setupOutput);
            $setupOutput->summary();
        } finally {
            putenv('NO_COLOR');
        }

        rewind($stream->getStream());
        $output = (string) stream_get_contents($stream->getStream());

        expect($stream->isDecorated())->toBeFalse()
            ->and($output)->not->toContain("\e[")
            ->and($output)->toContain('┌─ Summary')
            ->and($output)->toContain('✔ Two-Factor Authentication');
    });

    it('prints the header paths on one line each, keeping the end of a long path', function (): void {
        $buffer = new BufferedOutput();
        $setupOutput = new SetupOutput($buffer, new ConsoleProfile(40));

        $setupOutput->header('1.4.0', '/home/dev/projects/acme/backend-application', null);

        expect(visibleLines($buffer->fetch()))->toBe([
            '┌─ lightit-auth-laravel 1.4.0 ─────────┐',
            '│ back   …cts/acme/backend-application │',
            '│ front  none found, frontend steps    │',
            '│        are skipped                   │',
            '└──────────────────────────────────────┘',
        ]);
    });
});
