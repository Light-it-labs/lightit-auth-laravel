<?php

declare(strict_types=1);

use Lightitlabs\Auth\Frontend\FrontendPackageManifest;
use Lightitlabs\Auth\Frontend\FrontendProjectLocator;
use Lightitlabs\Auth\Frontend\FrontendUsageScanner;

$fixtures = __DIR__.'/../Fixtures/frontend';
$reactProject = $fixtures.'/react-project';
$apiProject = $fixtures.'/api-project';

$locator = static function (): FrontendProjectLocator {
    return new FrontendProjectLocator(new FrontendPackageManifest);
};

describe('FrontendProjectLocator', function () use (
    $locator,
    $reactProject,
    $apiProject,
): void {
    it('accepts an explicit path to a react-template-shaped project', function () use (
        $locator,
        $reactProject
    ): void {
        expect($locator()->locate('/tmp/whatever', $reactProject))->toBe(realpath($reactProject));
    });

    it('rejects a project whose package.json does not list react', function () use (
        $locator,
        $apiProject
    ): void {
        expect($locator()->locate('/tmp/whatever', $apiProject))->toBeNull();
    });

    it('rejects a react dependency that only sits in devDependencies', function () use (
        $locator
    ): void {
        $root = sys_get_temp_dir().'/lightit-dev-react-'.bin2hex(random_bytes(6));
        mkdir($root, 0755, true);
        file_put_contents(
            $root.'/package.json',
            json_encode(['devDependencies' => ['react' => '^19.2.3']])
        );

        expect($locator()->locate('/tmp/whatever', $root))->toBeNull();

        unlink($root.'/package.json');
        rmdir($root);
    });

    it('probes the three sibling candidates when no explicit path is given', function () use (
        $locator,
        $reactProject
    ): void {
        $parent = sys_get_temp_dir().'/lightit-probe-'.bin2hex(random_bytes(6));
        $laravelRoot = $parent.'/shop';
        mkdir($laravelRoot, 0755, true);

        expect($locator()->locate($laravelRoot))->toBeNull();

        symlink(realpath($reactProject), $parent.'/shop-frontend');

        expect($locator()->locate($laravelRoot))->toBe(realpath($reactProject));

        unlink($parent.'/shop-frontend');
        rmdir($laravelRoot);
        rmdir($parent);
    });

    it('refuses a destination that escapes the resolved root', function () use (
        $locator,
        $reactProject
    ): void {
        expect(function () use ($locator, $reactProject): void {
            $locator()->resolveDestination($reactProject, '../../escaped/api.ts');
        })->toThrow(RuntimeException::class, 'Refusing to write outside the frontend root');
    });

    it('resolves a destination inside the root', function () use ($locator, $reactProject): void {
        expect($locator()->resolveDestination($reactProject, 'src/config/api.ts'))
            ->toBe(realpath($reactProject).'/src/config/api.ts');
    });
});

describe('FrontendPackageManifest', function () use ($reactProject, $apiProject): void {
    it('reads the declared package manager', function () use ($reactProject): void {
        expect((new FrontendPackageManifest)->packageManager($reactProject))->toBe('pnpm');
        expect((new FrontendPackageManifest)->addCommand($reactProject))->toBe('pnpm add');
    });

    it('falls back to npm when nothing identifies a manager', function () use ($apiProject): void {
        expect((new FrontendPackageManifest)->packageManager($apiProject))->toBe('npm');
    });

    it('extracts the major version from a caret constraint', function (): void {
        $manifest = new FrontendPackageManifest;

        expect($manifest->majorVersion('^4.1.13'))->toBe(4);
        expect($manifest->majorVersion('~3.22.4'))->toBe(3);
        expect($manifest->majorVersion('workspace:*'))->toBeNull();
    });

    it('compares against a version floor', function (): void {
        $manifest = new FrontendPackageManifest;

        expect($manifest->satisfiesFloor('^1.13.5', '1.6.2'))->toBeTrue();
        expect($manifest->satisfiesFloor('^1.4.0', '1.6.2'))->toBeFalse();
    });
});

describe('FrontendUsageScanner', function () use ($reactProject): void {
    it('lists auth store readers and stale client call sites', function () use (
        $reactProject
    ): void {
        $scanner = new FrontendUsageScanner;

        // Inlined literals: FrontendUsageScanner takes the pattern as an argument
        // rather than reading it from an installer, so these tests use the same
        // regex a caller (e.g. a frontend installer) would pass in.
        expect($scanner->grep($reactProject, '/use-auth-store/'))
            ->toBe(['src/routes/logout-button.tsx:2']);

        expect($scanner->grep($reactProject, '/\b(?:publicApi|privateApi)\b/'))
            ->toBe([
                'src/routes/logout-button.tsx:1',
                'src/routes/logout-button.tsx:7',
            ]);
    });

    it('renders an empty result as a markdown placeholder line', function (): void {
        expect((new FrontendUsageScanner)->toMarkdownList([]))->toBe('- None found.');
    });

    it('orders hits naturally so line 4 precedes line 31', function (): void {
        $root = sys_get_temp_dir().'/lightit-natsort-'.bin2hex(random_bytes(6));
        mkdir($root.'/src', 0755, true);
        file_put_contents(
            $root.'/src/api.ts',
            implode("\n", array_map(static function (int $line): string {
                return \in_array($line, [4, 11, 31], true) ? 'publicApi.get("x");' : '';
            }, range(1, 31)))
        );

        expect((new FrontendUsageScanner)->grep($root, '/\b(?:publicApi|privateApi)\b/'))
            ->toBe(['src/api.ts:4', 'src/api.ts:11', 'src/api.ts:31']);

        unlink($root.'/src/api.ts');
        rmdir($root.'/src');
        rmdir($root);
    });
});
