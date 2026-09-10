<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Each of these proves that selecting exactly one of {Passkeys, Google SSO,
 * two-factor authentication} - and nothing else - produces a frontend layer
 * that type-checks on its own, with no import left dangling on a file only a
 * *different* optional feature would have generated (see the Copilot findings
 * about `@/services/auth/session` and `TwoFactorSection` being owned by the
 * wrong installer). This runs the real compiler against a copy of exactly the
 * files that feature's installer(s) write - not a reasoned claim about it.
 */
$frontendRoot = __DIR__.'/../Fixtures/frontend';

$copyFeatureFiles = function (string $projectDir, array $files) use ($frontendRoot): void {
    foreach ($files as $relative => $sourceRelative) {
        $destination = $projectDir.'/src/'.$relative;
        File::ensureDirectoryExists(dirname($destination));
        File::copy($frontendRoot.'/expected/'.$sourceRelative, $destination);
    }
};

$scaffoldProject = function (string $projectDir) use ($frontendRoot): void {
    File::ensureDirectoryExists($projectDir);
    File::copyDirectory($frontendRoot.'/app-stubs', $projectDir.'/app-stubs');
    File::copyDirectory($frontendRoot.'/consumer-stubs', $projectDir.'/consumer-stubs');

    file_put_contents($projectDir.'/tsconfig.json', json_encode([
        'compilerOptions' => [
            'target' => 'ES2022',
            'lib' => ['ES2022', 'DOM'],
            'module' => 'ESNext',
            'moduleResolution' => 'Bundler',
            'jsx' => 'react-jsx',
            'strict' => true,
            'verbatimModuleSyntax' => true,
            'esModuleInterop' => true,
            'forceConsistentCasingInFileNames' => true,
            'skipLibCheck' => true,
            'noEmit' => true,
            'baseUrl' => '.',
            'paths' => [
                '@/*' => ['./src/*', './consumer-stubs/*', './app-stubs/*'],
            ],
        ],
        'include' => ['src/**/*.ts', 'src/**/*.tsx', 'consumer-stubs/**/*.tsx', 'app-stubs/**/*.ts'],
    ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
};

$typeCheck = function (string $projectDir) use ($frontendRoot): Process {
    $process = new Process(
        ['npx', 'tsc', '--noEmit', '-p', $projectDir.'/tsconfig.json'],
        $frontendRoot,
    );
    $process->run();

    return $process;
};

describe('single-feature type-check', function () use ($scaffoldProject, $copyFeatureFiles, $typeCheck): void {
    beforeEach(function (): void {
        $this->projectDir = __DIR__.'/../Fixtures/frontend/.tmp-single-feature-'.bin2hex(random_bytes(6));
    });

    afterEach(function (): void {
        File::deleteDirectory($this->projectDir);
    });

    it('type-checks a Passkeys-only install on its own', function () use ($scaffoldProject, $copyFeatureFiles, $typeCheck): void {
        $scaffoldProject($this->projectDir);

        $copyFeatureFiles($this->projectDir, [
            'services/auth/session.ts' => 'src/services/auth/session.ts',
            'services/auth/passkeys/types.ts' => 'src/services/auth/passkeys/types.ts',
            'services/auth/passkeys/schemas.ts' => 'src/services/auth/passkeys/schemas.ts',
            'services/auth/passkeys/api.ts' => 'src/services/auth/passkeys/api.ts',
            'services/auth/passkeys/actions.ts' => 'src/services/auth/passkeys/actions.ts',
            'routes/(public)/_guest/login/-components/passkey-login-button.tsx' => 'src/routes/(public)/_guest/login/-components/passkey-login-button.tsx',
            'routes/_private/security/-components/passkeys-section.tsx' => 'src/routes/_private/security/-components/passkeys-section.tsx',
            'routes/_private/security/-components/enrol-passkey-dialog.tsx' => 'src/routes/_private/security/-components/enrol-passkey-dialog.tsx',
            'routes/_private/security/page.tsx' => 'src/routes/_private/security/page.passkeys-only.tsx',
        ]);

        $process = $typeCheck($this->projectDir);

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });

    it('type-checks a Google-SSO-only install on its own', function () use ($scaffoldProject, $copyFeatureFiles, $typeCheck): void {
        $scaffoldProject($this->projectDir);

        $copyFeatureFiles($this->projectDir, [
            'services/auth/session.ts' => 'src/services/auth/session.ts',
            'services/auth/sso/google/types.ts' => 'src/services/auth/sso/google/types.ts',
            'services/auth/sso/google/schemas.ts' => 'src/services/auth/sso/google/schemas.ts',
            'services/auth/sso/google/api.ts' => 'src/services/auth/sso/google/api.ts',
            'services/auth/sso/google/actions.ts' => 'src/services/auth/sso/google/actions.ts',
            'hooks/use-google-identity-services.ts' => 'src/hooks/use-google-identity-services.ts',
            'routes/(public)/_guest/login/-components/google-login-button.tsx' => 'src/routes/(public)/_guest/login/-components/google-login-button.tsx',
        ]);

        $process = $typeCheck($this->projectDir);

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });

    it('type-checks a two-factor-authentication-only install on its own', function () use ($scaffoldProject, $copyFeatureFiles, $typeCheck): void {
        $scaffoldProject($this->projectDir);

        $copyFeatureFiles($this->projectDir, [
            'services/auth/session.ts' => 'src/services/auth/session.ts',
            'services/auth/two-factor/types.ts' => 'src/services/auth/two-factor/types.ts',
            'services/auth/two-factor/schemas.ts' => 'src/services/auth/two-factor/schemas.ts',
            'services/auth/two-factor/api.ts' => 'src/services/auth/two-factor/api.ts',
            'services/auth/two-factor/actions.ts' => 'src/services/auth/two-factor/actions.ts',
            'routes/(public)/_guest/two-factor/setup/page.tsx' => 'src/routes/(public)/_guest/two-factor/setup/page.tsx',
            'routes/(public)/_guest/two-factor/-components/recovery-codes.tsx' => 'src/routes/(public)/_guest/two-factor/-components/recovery-codes.tsx',
            'routes/(public)/_guest/two-factor/page.tsx' => 'src/routes/(public)/_guest/two-factor/page.tsx',
            'routes/(public)/_guest/two-factor/recovery-code/page.tsx' => 'src/routes/(public)/_guest/two-factor/recovery-code/page.tsx',
            'routes/(public)/_guest/two-factor/reset/page.tsx' => 'src/routes/(public)/_guest/two-factor/reset/page.tsx',
            'routes/_private/security/-components/two-factor-section.tsx' => 'src/routes/_private/security/-components/two-factor-section.tsx',
            'routes/_private/security/-components/disable-two-factor-dialog.tsx' => 'src/routes/_private/security/-components/disable-two-factor-dialog.tsx',
            'routes/_private/security/-components/regenerate-recovery-codes-dialog.tsx' => 'src/routes/_private/security/-components/regenerate-recovery-codes-dialog.tsx',
            'routes/_private/security/-components/request-two-factor-reset-dialog.tsx' => 'src/routes/_private/security/-components/request-two-factor-reset-dialog.tsx',
            'routes/_private/security/page.tsx' => 'src/routes/_private/security/page.two-factor-only.tsx',
        ]);

        $process = $typeCheck($this->projectDir);

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });
});
