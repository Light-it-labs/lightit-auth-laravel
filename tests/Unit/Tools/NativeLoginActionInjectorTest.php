<?php

declare(strict_types=1);

use Lightitlabs\Tools\NativeLoginActionInjector;
use Lightitlabs\Tools\NativeLoginInjectionOutcome;

/**
 * The fixture loaded below is `Light-it-labs/laravel`'s own
 * `src/Authentication/Domain/Actions/LoginAction.php`, as it exists on
 * `feature/no-task/cookie-based-auth-sanctum` (PR #426) - a login this
 * package never generated. `LoginActionPatcher` doesn't recognise it (see
 * `LoginActionInstallerCollisionTest`'s consumer-edited-file case), so
 * `NativeLoginActionInjector` is what actually wires 2FA into it. Shared
 * with `LoginActionInstallerCollisionTest`, which exercises the same fixture
 * through `Google2FAInstaller`.
 */
function nativeLoginActionFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../../Fixtures/laravel-426-native-login-action.php.txt');
}

describe('NativeLoginActionInjector against laravel#426\'s real LoginAction', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/native-login-injector-'.uniqid();
        mkdir($this->directory, 0755, true);
        $this->destination = $this->directory.'/LoginAction.php';

        file_put_contents($this->destination, nativeLoginActionFixture());

        $this->injector = new NativeLoginActionInjector;
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    });

    it('patches it and reports success', function (): void {
        expect($this->injector->inject($this->destination))->toBe(NativeLoginInjectionOutcome::Patched);
    });

    it('preserves the native behaviour untouched: the rate-limit closures and the session regenerate', function (): void {
        $this->injector->inject($this->destination);
        $patched = (string) file_get_contents($this->destination);

        expect($patched)
            ->toContain('public function execute(Request $request, array $credentials, Closure $onFailure, Closure $onSuccess): User')
            ->toContain('if (! $guard->attempt($credentials)) {')
            ->toContain('$onFailure();')
            ->toContain('$onSuccess();')
            ->toContain('$request->session()->regenerate();');
    });

    it('makes the 2FA pipes reachable: the gate call sits after the rate-limit success hook and before the return', function (): void {
        $this->injector->inject($this->destination);
        $patched = (string) file_get_contents($this->destination);

        $onSuccessPosition = strpos($patched, '$onSuccess();');
        $gateCallPosition = strpos($patched, 'TwoFactorLoginGate::class)->guardAgainstChallenge($user);');
        $returnPosition = strrpos($patched, 'return $user;');

        expect($onSuccessPosition)->not->toBeFalse()
            ->and($gateCallPosition)->not->toBeFalse()
            ->and($returnPosition)->not->toBeFalse()
            ->and($gateCallPosition)->toBeGreaterThan($onSuccessPosition)
            ->and($gateCallPosition)->toBeLessThan($returnPosition);
    });

    it('produces syntactically valid PHP', function (): void {
        $this->injector->inject($this->destination);
        $patched = (string) file_get_contents($this->destination);

        expect(fn () => token_get_all($patched, TOKEN_PARSE))->not->toThrow(ParseError::class);
    });

    it('does not touch a failing attempt: $onFailure still runs and $onSuccess and the gate never do', function (): void {
        $this->injector->inject($this->destination);
        $patched = (string) file_get_contents($this->destination);

        $onFailurePosition = strpos($patched, '$onFailure();');
        $attemptCheckPosition = strpos($patched, 'if (! $guard->attempt($credentials)) {');
        $onSuccessPosition = strpos($patched, '$onSuccess();');

        // $onFailure() still fires inside the failed-attempt branch, strictly
        // before $onSuccess() and the gate - which only run once execution
        // reaches the success path below that branch.
        expect($attemptCheckPosition)->toBeLessThan($onFailurePosition)
            ->and($onFailurePosition)->toBeLessThan($onSuccessPosition);
    });

    it('is idempotent when the injection runs twice', function (): void {
        $this->injector->inject($this->destination);
        $firstPass = (string) file_get_contents($this->destination);

        $secondOutcome = $this->injector->inject($this->destination);
        $secondPass = (string) file_get_contents($this->destination);

        expect($secondOutcome)->toBe(NativeLoginInjectionOutcome::AlreadyApplied)
            ->and($secondPass)->toBe($firstPass)
            ->and(substr_count($secondPass, 'lightit-auth: 2fa challenge gate'))->toBe(1);
    });

    it('reports a shape it does not recognise instead of guessing at one', function (): void {
        file_put_contents($this->destination, "<?php\n\nclass LoginAction\n{\n    public function execute(): void {}\n}\n");

        $outcome = $this->injector->inject($this->destination);

        expect($outcome)->toBe(NativeLoginInjectionOutcome::AnchorNotFound)
            ->and((string) file_get_contents($this->destination))->toBe(
                "<?php\n\nclass LoginAction\n{\n    public function execute(): void {}\n}\n"
            );
    });
});
