<?php

declare(strict_types=1);

describe('feature docs', function (): void {
    beforeEach(function (): void {
        $this->packageRoot = dirname(__DIR__, 2);
        $this->readme = (string) file_get_contents($this->packageRoot . '/README.md');
    });

    it('links every page under docs/ from the README', function (): void {
        $pages = glob($this->packageRoot . '/docs/*.md');

        expect($pages)->not->toBeEmpty();

        foreach ($pages as $page) {
            expect($this->readme)->toContain('(docs/' . basename($page) . ')');
        }
    });

    it('explains, for each feature with checklists, which files auth:setup leaves and what to do with them', function (
        string $page,
        array $checklists,
    ): void {
        $doc = (string) file_get_contents($this->packageRoot . '/docs/' . $page);

        expect($doc)
            ->toContain('### After `auth:setup`')
            ->toContain('**delete the file when every box is ticked**')
            ->toContain('Running `auth:setup` again never overwrites it.');

        foreach ($checklists as $checklist) {
            expect($doc)->toContain('| `' . $checklist . '` |');
        }
    })->with([
        '2FA' => ['google-2fa.md', ['AUTH-2FA-TODO.md', 'AUTH-2FA-FRONTEND-TODO.md']],
        'Passkeys' => ['passkeys.md', ['AUTH-PASSKEYS-TODO.md', 'AUTH-PASSKEYS-FRONTEND-TODO.md']],
    ]);
});
