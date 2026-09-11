<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\FakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserNotExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * `TwoFactorLoginGate::guardAgainstChallenge()` - wired into every login via
 * the generated pipeline or `NativeLoginActionInjector` - calls methods that
 * only exist on `TwoFactorAuthenticatable`. `config/google2fa.php`'s
 * `enabled` and `mandatory` both default to `true`, so a consumer's User
 * model that doesn't extend it turns every login into a
 * `BadMethodCallException` with no config change required. This exercises
 * the guard directly, since `Google2FAInstaller::install()` itself shells
 * out to `composer require` and is not something a unit test should run.
 */
function invokeUserModelWarningGuard(string $userModelClass, string $requiredParentClass): array
{
    $command = new class extends Command
    {
        protected $signature = 'google2fa-user-model-warning-test';

        /** @var list<string> */
        public array $warnings = [];

        public function warn($string, $verbosity = null): void
        {
            $this->warnings[] = (string) $string;
        }
    };

    $installer = new Google2FAInstaller(
        $command,
        new ComposerInstaller(new class extends Command
        {
            protected $signature = 'google2fa-user-model-warning-test-composer';
        }),
        new StubCopier(new OriginMarker('0.0.0-test')),
    );

    $guard = new ReflectionMethod($installer, 'warnIfUserModelCannotSupportTwoFactor');
    $guard->setAccessible(true);
    $guard->invoke($installer, $userModelClass, $requiredParentClass);

    return $command->warnings;
}

describe('Google2FAInstaller warns instead of failing when the User model cannot support 2FA', function (): void {
    it('warns when the consumer User model class does not exist, and does not throw', function (): void {
        $warnings = invokeUserModelWarningGuard(
            'Lightitlabs\\Tests\\Fixtures\\Google2FAUserModel\\MissingUser',
            FakeTwoFactorAuthenticatable::class,
        );

        expect($warnings)->toHaveCount(1);
        expect($warnings[0])->toContain('Could not find');
    });

    it('warns when the consumer User model does not extend the two-factor authenticatable base class, and does not throw', function (): void {
        $warnings = invokeUserModelWarningGuard(
            UserNotExtendingFakeTwoFactorAuthenticatable::class,
            FakeTwoFactorAuthenticatable::class,
        );

        expect($warnings)->toHaveCount(1);
        expect($warnings[0])->toContain('does not extend');
    });

    it('stays silent when the consumer User model extends the two-factor authenticatable base class', function (): void {
        $warnings = invokeUserModelWarningGuard(
            UserExtendingFakeTwoFactorAuthenticatable::class,
            FakeTwoFactorAuthenticatable::class,
        );

        expect($warnings)->toBe([]);
    });
});

describe("install()'s own call order", function (): void {
    /**
     * A live validation run hit an install that could never succeed: the
     * guard ran before `createAuthFiles()` had written
     * `TwoFactorAuthenticatable.php`, so it demanded the consumer's User
     * model extend a class that did not exist yet on every single install.
     * `Google2FAInstallerUserModelWarningTest`'s other tests exercise the
     * guard in isolation with both fixture classes already present, so they
     * could never see that ordering bug - this inspects `install()`'s own
     * source so a future edit that swaps the two calls back fails loudly
     * here instead of on a live install.
     */
    it('writes the auth files before checking whether the User model can support 2FA', function (): void {
        $install = new ReflectionMethod(Google2FAInstaller::class, 'install');
        $lines = (array) file((string) $install->getFileName());
        $body = implode('', array_slice(
            $lines,
            $install->getStartLine() - 1,
            $install->getEndLine() - $install->getStartLine() + 1,
        ));

        $createAuthFilesPosition = strpos($body, 'createAuthFiles(');
        $warnGuardPosition = strpos($body, 'warnIfUserModelCannotSupportTwoFactor(');

        expect($createAuthFilesPosition)->not->toBeFalse();
        expect($warnGuardPosition)->not->toBeFalse();
        expect($createAuthFilesPosition)->toBeLessThan($warnGuardPosition);
    });
});

describe("install()'s real create-then-warn sequence, against a real fresh project", function (): void {
    beforeEach(function (): void {
        require_once __DIR__.'/../../../Fixtures/Google2FAUserModel/RealNamespaceNonConformingUser.php';

        $this->tempBase = sys_get_temp_dir().'/google2fa-user-model-warning-'.uniqid();
        mkdir($this->tempBase, 0755, true);
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);
    });

    afterEach(function (): void {
        $this->app->setBasePath($this->originalBasePath);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempBase, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->tempBase);
    });

    it('writes TwoFactorAuthenticatable.php, then warns against the real User model FQCN instead of failing the install', function (): void {
        $command = new class extends Command
        {
            protected $signature = 'google2fa-user-model-warning-sequence-test';

            /** @var list<string> */
            public array $warnings = [];

            public function warn($string, $verbosity = null): void
            {
                $this->warnings[] = (string) $string;
            }

            public function handle(): int
            {
                $composerInstaller = new ComposerInstaller($this);
                $installer = new Google2FAInstaller($this, $composerInstaller, new StubCopier(new OriginMarker('0.0.0-test')));

                // Mirrors install()'s own order: write the auth files - the
                // step that produces TwoFactorAuthenticatable.php - before
                // checking whether the User model extends it.
                $createAuthFiles = new ReflectionMethod($installer, 'createAuthFiles');
                $createAuthFiles->setAccessible(true);
                $createAuthFiles->invoke($installer);

                $warnGuard = new ReflectionMethod($installer, 'warnIfUserModelCannotSupportTwoFactor');
                $warnGuard->setAccessible(true);
                $warnGuard->invoke($installer);

                return self::SUCCESS;
            }
        };

        $command->setLaravel($this->app);
        $command->run(new ArrayInput([]), new NullOutput);

        expect(file_exists($this->tempBase.'/src/Authentication/Domain/TwoFactorAuthenticatable.php'))->toBeTrue();

        expect($command->warnings)->toHaveCount(1);
        expect($command->warnings[0])
            ->toContain('Lightit\Users\Domain\Models\User')
            ->toContain('does not extend')
            ->toContain('Lightit\Authentication\Domain\TwoFactorAuthenticatable');
    });
});
