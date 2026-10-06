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

    it('points to the checklists auth:setup leaves instead of repeating their steps', function (
        string $page,
        array $checklists,
    ): void {
        $doc = (string) file_get_contents($this->packageRoot . '/docs/' . $page);

        expect($doc)
            ->toContain('### Install')
            ->toContain('**The steps live there, not on this page:**')
            ->toContain('**delete the file when every box is ticked**')
            ->not->toMatch('/^#{2,4} \d+\./m');

        foreach ($checklists as $checklist) {
            expect($doc)->toContain('| `' . $checklist . '` |');
        }
    })->with([
        '2FA' => ['google-2fa.md', ['AUTH-2FA-TODO.md', 'AUTH-2FA-FRONTEND-TODO.md']],
    ]);
});
