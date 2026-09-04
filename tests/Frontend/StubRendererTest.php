<?php

declare(strict_types=1);

use Lightitlabs\Tools\StubRenderer;

$writeStub = static function (string $contents): string {
    $path = sys_get_temp_dir().'/lightit-stub-'.bin2hex(random_bytes(6)).'.stub';
    file_put_contents($path, $contents);

    return $path;
};

describe('StubRenderer', function () use ($writeStub): void {
    afterEach(function (): void {
        array_map('unlink', glob(sys_get_temp_dir().'/lightit-stub-*.stub') ?: []);
    });

    it('substitutes spaced and unspaced placeholders', function () use ($writeStub): void {
        $stub = $writeStub('{{ alpha }} and {{beta}}');

        expect((new StubRenderer)->render($stub, ['alpha' => 'a', 'beta' => 'b']))
            ->toBe('a and b');
    });

    it('does not cascade when one value contains another placeholder', function () use (
        $writeStub
    ): void {
        // An array str_replace() would apply the pairs in sequence over the
        // already-replaced subject and yield "resolved|resolved". strtr() is a
        // single longest-match pass, so the value stays verbatim. The token is
        // capitalised only so the leftover guard, which matches lowercase names,
        // does not fire on the deliberately unresolved output.
        $stub = $writeStub('{{ outer }}|{{ Inner }}');

        expect((new StubRenderer)->render($stub, [
            'outer' => '{{ Inner }}',
            'Inner' => 'resolved',
        ]))->toBe('{{ Inner }}|resolved');
    });

    it('rejects a stub with an unresolved placeholder', function () use ($writeStub): void {
        $stub = $writeStub('const a = "{{ missingToken }}";');

        expect(function () use ($stub): void {
            (new StubRenderer)->render($stub, []);
        })->toThrow(RuntimeException::class, 'Unresolved placeholder {{ missingToken }}');
    });

    it('leaves shell and template braces alone', function () use ($writeStub): void {
        $stub = $writeStub('${id} and {{ Uppercase }} and { single }');

        expect((new StubRenderer)->render($stub, []))
            ->toBe('${id} and {{ Uppercase }} and { single }');
    });

    it('creates the destination directory before writing', function () use ($writeStub): void {
        $stub = $writeStub('{{ body }}');
        $destination = sys_get_temp_dir().'/lightit-render-'.bin2hex(random_bytes(6))
            .'/deep/nested/out.ts';

        (new StubRenderer)->renderTo($stub, $destination, ['body' => 'written']);

        expect(file_get_contents($destination))->toBe('written');

        unlink($destination);
    });

    it('fails loudly on a missing stub', function (): void {
        expect(function (): void {
            (new StubRenderer)->render('/nonexistent/nope.stub', []);
        })->toThrow(RuntimeException::class, 'Unable to read stub');
    });
});
