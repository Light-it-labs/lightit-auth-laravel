<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Lightitlabs\Auth\Installers\ComposerInstaller;
use Lightitlabs\Auth\Installers\Google2FAInstaller;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FAInstallerCommand;
use Lightitlabs\Tests\Fixtures\RecordingSetupReporter;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

describe('Google2FAInstaller', function (): void {
    beforeEach(function (): void {
        $this->tempBase = sys_get_temp_dir() . '/google2fa-installer-' . bin2hex(random_bytes(6));
        mkdir($this->tempBase, 0755, true);
        mkdir($this->tempBase . '/database/migrations', 0755, true);
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->tempBase);

        $this->newFiles = [
            'src/Authentication/Domain/Actions/LoginByUserAction.php',
            'src/Authentication/Domain/Actions/IssueTwoFactorChallengeAction.php',
            'src/Authentication/Domain/Exceptions/TwoFactorChallengeException.php',
            'src/Authentication/Domain/TwoFactorAuthenticatable.php',
            'src/Authentication/Domain/TwoFactorAttemptLimiter.php',
            'src/Authentication/Domain/Actions/CompleteTwoFactorAuthenticationAction.php',
            'src/Authentication/Domain/Actions/VerifyRecoveryCodeAction.php',
            'src/Authentication/Domain/Actions/ConsumeRecoveryCodeAction.php',
            'src/Authentication/Domain/Actions/VerifyTwoFactorCodeAction.php',
            'src/Authentication/Domain/Actions/EnableTwoFactorAuthenticationAction.php',
            'src/Authentication/Domain/Actions/ConfirmTwoFactorAuthenticationAction.php',
            'src/Authentication/Domain/Actions/RegenerateRecoveryCodesAction.php',
            'src/Authentication/Domain/DataTransferObjects/TwoFactorEnrollmentDto.php',
            'src/Authentication/App/Resources/TwoFactorEnrollmentResource.php',
            'src/Authentication/App/Resources/TwoFactorRecoveryCodesResource.php',
            'src/Authentication/App/Resources/TwoFactorStatusResource.php',
            'src/Authentication/App/Requests/EnableTwoFactorAuthenticationRequest.php',
            'src/Authentication/App/Requests/ConfirmTwoFactorAuthenticationRequest.php',
            'src/Authentication/App/Controllers/EnableTwoFactorAuthenticationController.php',
            'src/Authentication/App/Controllers/ConfirmTwoFactorAuthenticationController.php',
            'src/Authentication/App/Controllers/ShowTwoFactorAuthenticationStatusController.php',
            'AUTH-2FA-TODO.md',
        ];
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

    it('writes the shared login primitives, the new Google2FA action stubs and the manual TODO doc', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());

        $this->artisan('google2fa-installer-fake')->assertSuccessful();

        foreach ($this->newFiles as $relative) {
            expect(file_exists($this->tempBase . '/' . $relative))->toBeTrue();
        }
    });

    it(
        'prints the constructor injection snippet for LoginAction, matching AUTH-2FA-TODO.md, without a same-namespace import or app()',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->doesntExpectOutputToContain(
                    'use Lightit\Authentication\Domain\Actions\IssueTwoFactorChallengeAction;'
                )
                ->expectsOutputToContain(
                    'private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,'
                )
                ->doesntExpectOutputToContain('app(')
                ->assertSuccessful();
    
            $todo = file_get_contents($this->tempBase . '/AUTH-2FA-TODO.md');
    
            expect($todo)
                ->not->toContain('use Lightit\Authentication\Domain\Actions\IssueTwoFactorChallengeAction;')
                ->toContain('private readonly IssueTwoFactorChallengeAction $issueTwoFactorChallengeAction,')
                ->not->toContain('app(');
        }
    );

    it(
        'prints the call to the injected challenge action, right before return $user;, matching AUTH-2FA-TODO.md',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->expectsOutputToContain('$this->issueTwoFactorChallengeAction->execute($user);')
                ->assertSuccessful();
    
            expect(file_get_contents($this->tempBase . '/AUTH-2FA-TODO.md'))
                ->toContain('$this->issueTwoFactorChallengeAction->execute($user);');
        }
    );

    it('never asks for an AppServiceProvider paste: the package provider registers the 2fa limiter', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());

        $this->artisan('google2fa-installer-fake')
            ->doesntExpectOutputToContain('AppServiceProvider')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBase . '/AUTH-2FA-TODO.md'))->not->toContain('AppServiceProvider');
    });

    it('writes the TODO as a checklist to delete when done, with every placeholder resolved', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());

        $this->artisan('google2fa-installer-fake')->assertSuccessful();

        $todo = (string) file_get_contents($this->tempBase . '/AUTH-2FA-TODO.md');

        expect($todo)
            ->toStartWith("# 2FA setup checklist (backend)\n\n> Generated by `php artisan auth:setup`")
            ->toContain('then **delete this file**')
            ->toContain('docs/google-2fa.md')
            ->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/')
            ->and(substr_count($todo, "\n- [ ] "))->toBe(5);
    });

    it('summarises each manual step as its checklist title and the file it edits', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());

        $this->artisan('google2fa-installer-fake')
            ->expectsOutputToContain(
                '1. Wire the challenge into LoginAction · src/Authentication/Domain/Actions/LoginAction.php'
            )
            ->expectsOutputToContain('2. Make User support 2FA · src/Users/Domain/Models/User.php')
            ->expectsOutputToContain('3. Run php artisan migrate')
            ->expectsOutputToContain('4. Pick the mode · .env')
            ->expectsOutputToContain('Full steps AUTH-2FA-TODO.md (back)')
            ->assertSuccessful();
    });

    it('takes every printed step title word for word from AUTH-2FA-TODO.md, in its order', function (): void {
        $reporter = new RecordingSetupReporter();
        $installer = new Google2FAInstaller(
            $reporter,
            new ComposerInstaller($reporter),
            new StubCopier(new OriginMarker('0.0.0-test')),
        );

        $writeGuide = new ReflectionMethod($installer, 'writeManualIntegrationGuide');
        $writeGuide->invoke($installer);

        $todo = (string) file_get_contents($this->tempBase . '/AUTH-2FA-TODO.md');
        $offset = 0;

        expect($reporter->manualSteps)->toHaveCount(4);

        foreach ($reporter->manualSteps as $step) {
            $position = strpos($todo, '- [ ] **' . $step['title'] . '**', $offset);

            expect($position)->not->toBeFalse("\"{$step['title']}\" is not a checklist item after the previous one");

            $offset = (int) $position;

            if ($step['file'] !== null) {
                expect($todo)->toContain($step['file']);
            }
        }
    });

    it('reports Skipped instead of recreating any file on a second run', function (): void {
        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
        $this->artisan('google2fa-installer-fake')->assertSuccessful();

        $filesAfterFirstRun = [];
        foreach ($this->newFiles as $relative) {
            $filesAfterFirstRun[$relative] = file_get_contents($this->tempBase . '/' . $relative);
        }

        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
        $command = $this->artisan('google2fa-installer-fake');

        foreach ($this->newFiles as $relative) {
            $command->expectsOutputToContain('Skipped ' . $relative);
        }

        $command->assertSuccessful();

        foreach ($filesAfterFirstRun as $relative => $contentsAfterFirstRun) {
            expect(file_get_contents($this->tempBase . '/' . $relative))->toBe($contentsAfterFirstRun);
        }
    });

    it(
        'writes the package config with mandatory and challenge_ttl_minutes, without ever calling vendor:publish',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->doesntExpectOutputToContain('Publishing configuration')
                ->assertSuccessful();
    
            // Asserted from the source text, not `require`d: the config
            // references `TwoFactorAuthenticatable` and PragmaRX's own
            // `Constants` class, neither of which this package's own test
            // process autoloads (both only exist in the consuming project once
            // the package and its stubs are installed there).
            $config = (string) file_get_contents($this->tempBase . '/config/google2fa.php');
    
            expect($config)
                ->toContain("'enabled' =>")
                ->and($config)->toContain("'mandatory' =>")
                ->and($config)->toContain("'challenge_ttl_minutes' =>");
        }
    );

    it(
        'never overwrites an already-published config/google2fa.php, even one shaped like a bare vendor publish',
        function (): void {
            mkdir($this->tempBase . '/config', 0755, true);
            file_put_contents(
                $this->tempBase . '/config/google2fa.php',
                "<?php\n\nreturn ['enabled' => true, 'lifetime' => 0, 'guard' => ''];\n",
            );
    
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->expectsOutputToContain('Skipped config/google2fa.php')
                ->assertSuccessful();
    
            $config = require $this->tempBase . '/config/google2fa.php';
    
            expect($config)->toBe(['enabled' => true, 'lifetime' => 0, 'guard' => '']);
        }
    );

    it(
        'warns that password login stays single-factor when LoginAction does not exist at the expected path',
        function (): void {
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->expectsOutputToContain(
                    '! src/Authentication/Domain/Actions/LoginAction.php not found — password login stays '
                    . 'single-factor (step 1)'
                )
                ->assertSuccessful();
        }
    );

    it(
        'warns that password login stays single-factor when LoginAction exists but never references IssueTwoFactorChallengeAction',
        function (): void {
            mkdir($this->tempBase . '/src/Authentication/Domain/Actions', 0755, true);
            file_put_contents(
                $this->tempBase . '/src/Authentication/Domain/Actions/LoginAction.php',
                "<?php\n\nclass LoginAction\n{\n    public function execute(): void {}\n}\n",
            );
    
            Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());
    
            $this->artisan('google2fa-installer-fake')
                ->expectsOutputToContain(
                    '! LoginAction does not call IssueTwoFactorChallengeAction — password login stays '
                    . 'single-factor (step 1)'
                )
                ->assertSuccessful();
        }
    );

    it('stays silent about LoginAction when it already references IssueTwoFactorChallengeAction', function (): void {
        mkdir($this->tempBase . '/src/Authentication/Domain/Actions', 0755, true);
        file_put_contents(
            $this->tempBase . '/src/Authentication/Domain/Actions/LoginAction.php',
            "<?php\n\nuse Lightit\Authentication\Domain\Actions\IssueTwoFactorChallengeAction;\n\n"
            . "class LoginAction\n{\n    public function __construct(\n"
            . "        private readonly IssueTwoFactorChallengeAction \$issueTwoFactorChallengeAction,\n"
            . "    ) {}\n}\n",
        );

        Artisan::registerCommand(new FakeGoogle2FAInstallerCommand());

        $this->artisan('google2fa-installer-fake')
            ->doesntExpectOutputToContain('password login stays single-factor')
            ->assertSuccessful();
    });
});
