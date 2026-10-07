<?php

declare(strict_types=1);

use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\FakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\Google2FAUserModel\UserNotExtendingFakeTwoFactorAuthenticatable;
use Lightitlabs\Tests\Fixtures\RecordingSetupReporter;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

/**
 * `IssueTwoFactorChallengeAction::execute()` - wired manually into the
 * consumer's own login - only acts on a `User` that is also a
 * `TwoFactorAuthenticatable`. `config/google2fa.php`'s `enabled` and
 * `mandatory` both default to `true`, so a consumer's User model that
 * doesn't extend it throws a `LogicException` on every login with no config change
 * required. This exercises the guard directly, since
 * `Google2FAInstaller::install()` itself shells out to `composer require`
 * and is not something a unit test should run.
 */
function invokeUserModelWarningGuard(string $userModelClass, string $requiredParentClass): array
{
    $reporter = new RecordingSetupReporter();

    $installer = new Google2FAInstaller(
        $reporter,
        new ComposerInstaller($reporter),
        new StubCopier(new OriginMarker('0.0.0-test')),
    );

    $guard = new ReflectionMethod($installer, 'warnIfUserModelCannotSupportTwoFactor');
    $guard->setAccessible(true);
    $guard->invoke($installer, $userModelClass, $requiredParentClass);

    return $reporter->warnings;
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

    it(
        'warns when the consumer User model does not extend the two-factor authenticatable base class, and does not throw',
        function (): void {
            $warnings = invokeUserModelWarningGuard(
                UserNotExtendingFakeTwoFactorAuthenticatable::class,
                FakeTwoFactorAuthenticatable::class,
            );
    
            expect($warnings)->toHaveCount(1);
            expect($warnings[0])->toContain('does not extend');
        }
    );

    it(
        'stays silent when the consumer User model extends the two-factor authenticatable base class',
        function (): void {
            $warnings = invokeUserModelWarningGuard(
                UserExtendingFakeTwoFactorAuthenticatable::class,
                FakeTwoFactorAuthenticatable::class,
            );
    
            expect($warnings)->toBe([]);
        }
    );
});

describe("install()'s real create-then-warn sequence, against a real fresh project", function (): void {
    beforeEach(function (): void {
        require_once __DIR__ . '/../../../Fixtures/Google2FAUserModel/RealNamespaceNonConformingUser.php';

        $this->tempBase = sys_get_temp_dir() . '/google2fa-user-model-warning-' . uniqid();
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

    it(
        'writes TwoFactorAuthenticatable.php, then warns against the real User model FQCN instead of failing the install',
        function (): void {
            $reporter = new RecordingSetupReporter();
            $installer = new Google2FAInstaller(
                $reporter,
                new ComposerInstaller($reporter),
                new StubCopier(new OriginMarker('0.0.0-test'))
            );
    
            // Mirrors install()'s own order: write the auth files - the
            // step that produces TwoFactorAuthenticatable.php - before
            // checking whether the User model extends it.
            $createAuthFiles = new ReflectionMethod($installer, 'createAuthFiles');
            $createAuthFiles->setAccessible(true);
            $createAuthFiles->invoke($installer);
    
            $warnGuard = new ReflectionMethod($installer, 'warnIfUserModelCannotSupportTwoFactor');
            $warnGuard->setAccessible(true);
            $warnGuard->invoke($installer);
    
            expect(
                file_exists($this->tempBase . '/src/Authentication/Domain/TwoFactorAuthenticatable.php')
            )->toBeTrue();
    
            expect($reporter->warnings)->toHaveCount(1);
            expect($reporter->warnings[0])
                ->toContain('Lightit\Users\Domain\Models\User')
                ->toContain('does not extend')
                ->toContain('Lightit\Authentication\Domain\TwoFactorAuthenticatable');
        }
    );
});
