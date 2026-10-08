<?php

declare(strict_types=1);

namespace Lightitlabs\Console;

final class FeatureReport
{
    /**
     * @var list<string>
     */
    public array $written = [];

    /**
     * @var list<string>
     */
    public array $skipped = [];

    /**
     * @var list<array{title: string, file: string|null, details: list<string>}>
     */
    public array $manualSteps = [];

    /**
     * @var list<string>
     */
    public array $warnings = [];

    /**
     * @var list<array{message: string, details: list<string>}>
     */
    public array $errors = [];

    public string|null $failure = null;

    public function __construct(
        public readonly string $label,
        public readonly string|null $docs = null,
    ) {
    }

    public function failed(): bool
    {
        return $this->failure !== null || $this->errors !== [];
    }

    /**
     * @return list<string>
     */
    public function checklists(): array
    {
        return array_values(array_unique(array_filter(
            [...$this->written, ...$this->skipped],
            static fn (string $path): bool => preg_match('/(^|\/)AUTH-[A-Z0-9-]+-TODO\.md$/', $path) === 1,
        )));
    }
}
