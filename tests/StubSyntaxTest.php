<?php

declare(strict_types=1);

const STUB_SCAN_EXCLUDED_DIRECTORIES = ['/vendor/', '/.git/', '/node_modules/'];

/**
 * @return list<string>
 */
function phpStubPaths(): array
{
    $packageRoot = realpath(__DIR__ . '/..');

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

        if (! str_starts_with((string) file_get_contents($pathname), '<?php')) {
            continue;
        }

        $paths[] = substr($pathname, strlen($packageRoot) + 1);
    }

    sort($paths);

    return $paths;
}

function readStub(string $relativePath): string
{
    return (string) file_get_contents(__DIR__ . '/../' . $relativePath);
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
            ->toStartWith('<?php' . PHP_EOL . PHP_EOL . 'declare(strict_types=1);');
    })->with('phpClassStubs');
});
