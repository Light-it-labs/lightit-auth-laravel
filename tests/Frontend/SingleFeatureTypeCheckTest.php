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
 *
 * Critically, it also runs the real TanStack Router codegen (`tsr generate`) over
 * that copy before type-checking it, exactly like the Vite plugin would in a real
 * consuming project. A committed, hand-maintained stand-in for the generated route
 * tree would go stale the moment a route stopped registering, or stopped declaring
 * what search params it accepts, and `tsc` would never know - which is exactly the
 * blind spot that let a previous defect ship.
 */
$frontendRoot = __DIR__.'/../Fixtures/frontend';
$tsc = $frontendRoot.'/node_modules/.bin/tsc';
$tsr = $frontendRoot.'/node_modules/.bin/tsr';

$copyFeatureFiles = function (string $projectDir, array $files) use ($frontendRoot): void {
    foreach ($files as $relative => $sourceRelative) {
        $destination = $projectDir.'/src/'.$relative;
        File::ensureDirectoryExists(dirname($destination));
        File::copy($frontendRoot.'/expected/'.$sourceRelative, $destination);
    }
};

$scaffoldProject = function (string $projectDir) use ($frontendRoot): void {
    File::ensureDirectoryExists($projectDir);

    // The app shell a consuming project already has - a root route, a home route,
    // and the router registration that makes TanStack Router's types strict at all
    // (see app-stubs/config/router.ts) - lives under src/routes alongside whatever
    // this feature's installer writes, exactly like it would in a real project.
    File::copyDirectory($frontendRoot.'/app-stubs/routes', $projectDir.'/src/routes');
    File::copyDirectory($frontendRoot.'/app-stubs/config', $projectDir.'/app-stubs/config');
    File::copyDirectory($frontendRoot.'/app-stubs/services', $projectDir.'/app-stubs/services');
    File::copyDirectory($frontendRoot.'/consumer-stubs', $projectDir.'/consumer-stubs');

    file_put_contents($projectDir.'/tsr.config.json', json_encode([
        'generatedRouteTree' => './src/routeTree.gen.ts',
        'indexToken' => 'page',
        'quoteStyle' => 'single',
        'routeFileIgnorePrefix' => '-',
        'routesDirectory' => './src/routes',
        'routeToken' => 'layout',
    ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

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

$generateRouteTree = function (string $projectDir) use ($tsr): Process {
    $process = new Process([$tsr, 'generate'], $projectDir);
    $process->run();

    return $process;
};

$typeCheck = function (string $projectDir) use ($tsc): Process {
    $process = new Process(
        [$tsc, '--noEmit', '-p', $projectDir.'/tsconfig.json'],
        $projectDir,
    );
    $process->run();

    return $process;
};

describe('single-feature type-check', function () use ($scaffoldProject, $copyFeatureFiles, $generateRouteTree, $typeCheck): void {
    beforeEach(function (): void {
        $this->projectDir = __DIR__.'/../Fixtures/frontend/.tmp-single-feature-'.bin2hex(random_bytes(6));
    });

    afterEach(function (): void {
        File::deleteDirectory($this->projectDir);
    });

    it('type-checks a two-factor-authentication-only install on its own', function () use ($scaffoldProject, $copyFeatureFiles, $generateRouteTree, $typeCheck): void {
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

        $generation = $generateRouteTree($this->projectDir);

        expect($generation->isSuccessful())->toBeTrue($generation->getOutput().$generation->getErrorOutput());

        $process = $typeCheck($this->projectDir);

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });
});
