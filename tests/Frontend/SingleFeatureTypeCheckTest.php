<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Proves that a two-factor-authentication-only install - exactly the service layer
 * and the four login-flow screens this package generates, nothing an account-
 * management screen or a different login method would add - type-checks on its
 * own, with no import left dangling on a file only a different install would have
 * generated. This runs the real compiler against a copy of exactly the files this
 * feature's installer writes - not a reasoned claim about it.
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
        ]);

        $process = $typeCheck($this->projectDir);

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });
});
