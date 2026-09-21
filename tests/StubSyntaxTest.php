<?php

declare(strict_types=1);

use Lightitlabs\Auth\Permissions\PermissionCatalog;
use Lightitlabs\Tools\StubRenderer;

const STUB_SCAN_EXCLUDED_DIRECTORIES = ['/vendor/', '/.git/', '/node_modules/'];

/**
 * A `{{ lowercaseIdentifier }}` placeholder left unresolved makes a stub invalid
 * raw PHP by construction (see phpTemplateStubPaths()) — the same leftover-token
 * shape StubRenderer itself guards against.
 */
const TEMPLATE_TOKEN_PATTERN = '/\{\{\s*[a-z][a-zA-Z]*\s*\}\}/';

/**
 * @return list<string>
 */
function phpStubPaths(): array
{
    $packageRoot = realpath(__DIR__.'/..');

    if ($packageRoot === false) {
        throw new RuntimeException('Package root is not readable; the stub suite would silently validate nothing.');
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS)
    );

    $paths = [];

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'stub') {
            continue;
        }

        $pathname = $file->getPathname();

        foreach (STUB_SCAN_EXCLUDED_DIRECTORIES as $excludedDirectory) {
            if (str_contains($pathname, $excludedDirectory)) {
                continue 2;
            }
        }

        $contents = file_get_contents($pathname);

        if ($contents === false) {
            throw new RuntimeException("Unable to read stub file: {$pathname}");
        }

        if (! str_starts_with($contents, '<?php')) {
            continue;
        }

        // Tokenised stubs contain unresolved `{{ token }}` placeholders and are
        // not valid raw PHP until rendered — they get their own dataset below.
        if (preg_match(TEMPLATE_TOKEN_PATTERN, $contents) === 1) {
            continue;
        }

        $paths[] = substr($pathname, strlen($packageRoot) + 1);
    }

    sort($paths);

    return $paths;
}

/**
 * The PHP stubs rendered from PermissionCatalog — see phpStubPaths()'s exclusion
 * of the same paths above.
 *
 * @return list<string>
 */
function phpTemplateStubPaths(): array
{
    return [
        'LaravelPermissions/Permissions/UserPermissions.stub',
        'LaravelPermissions/Permissions/RolePermissions.stub',
        'LaravelPermissions/Permissions/PermissionManagement.stub',
    ];
}

function readStub(string $relativePath): string
{
    $path = __DIR__.'/../'.$relativePath;
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Unable to read stub file: {$path}");
    }

    return $contents;
}

/**
 * @return array<string, string>
 */
function templateStubTokens(): array
{
    $catalog = new PermissionCatalog;

    return [
        'userPermissionConstants' => $catalog->toPhpConstants('UserPermissions'),
        'rolePermissionConstants' => $catalog->toPhpConstants('RolePermissions'),
        'permissionRegistry' => $catalog->toPhpRegistry(),
    ];
}

function renderTemplateStub(string $relativePath): string
{
    return (new StubRenderer)->render(__DIR__.'/../src/Stubs/'.$relativePath, templateStubTokens());
}

dataset('phpStubs', function (): Generator {
    foreach (phpStubPaths() as $path) {
        yield $path => [$path];
    }
});

dataset('phpClassStubs', function (): Generator {
    foreach (phpStubPaths() as $path) {
        if (str_contains($path, '/lang/')) {
            continue;
        }

        yield $path => [$path];
    }
});

dataset('phpTemplateStubs', function (): Generator {
    foreach (phpTemplateStubPaths() as $path) {
        yield $path => [$path];
    }
});

describe('PHP stubs', function (): void {
    it('parses without a syntax error', function (string $relativePath): void {
        $error = null;

        try {
            token_get_all(readStub($relativePath), TOKEN_PARSE);
        } catch (ParseError $parseError) {
            $error = $parseError->getMessage();
        }

        expect($error)->toBeNull();
    })->with('phpStubs');

    it('opens with a strict types declaration', function (string $relativePath): void {
        expect(readStub($relativePath))
            ->toStartWith('<?php'.PHP_EOL.PHP_EOL.'declare(strict_types=1);');
    })->with('phpClassStubs');
});

describe('tokenised PHP stubs', function (): void {
    it('parses without a syntax error once rendered', function (string $relativePath): void {
        $error = null;

        try {
            token_get_all(renderTemplateStub($relativePath), TOKEN_PARSE);
        } catch (ParseError $parseError) {
            $error = $parseError->getMessage();
        }

        expect($error)->toBeNull();
    })->with('phpTemplateStubs');

    it('opens with a strict types declaration once rendered', function (string $relativePath): void {
        expect(renderTemplateStub($relativePath))
            ->toStartWith('<?php'.PHP_EOL.PHP_EOL.'declare(strict_types=1);');
    })->with('phpTemplateStubs');
});
