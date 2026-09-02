<?php

declare(strict_types=1);

/**
 * @return list<string>
 */
function phpStubPaths(): array
{
    $stubsPath = realpath(__DIR__.'/../src/Stubs');

    if ($stubsPath === false) {
        return [];
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stubsPath, FilesystemIterator::SKIP_DOTS)
    );

    $paths = [];

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'stub') {
            continue;
        }

        if (! str_starts_with((string) file_get_contents($file->getPathname()), '<?php')) {
            continue;
        }

        $paths[] = substr($file->getPathname(), strlen($stubsPath) + 1);
    }

    sort($paths);

    return $paths;
}

function readStub(string $relativePath): string
{
    return (string) file_get_contents(__DIR__.'/../src/Stubs/'.$relativePath);
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
            ->toStartWith('<?php'.PHP_EOL.PHP_EOL.'declare(strict_types=1);');
    })->with('phpClassStubs');
});
