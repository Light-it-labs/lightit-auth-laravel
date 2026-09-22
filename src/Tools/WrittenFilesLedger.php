<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

/**
 * Tracks destination paths written by StubCopier during a single `auth:setup`
 * invocation.
 *
 * Several installers can be configured to write to the same destination -
 * e.g. SanctumInstaller and Google2FAInstaller both generate
 * Domain/Actions/LoginAction.php, and the later one is meant to replace the
 * earlier one when both are selected in the same run. A path recorded here
 * was written moments ago by this package, in this run, so it is safe to
 * overwrite. Anything else - a file left over from a previous run, or one a
 * consumer wrote or edited by hand - was not written by this ledger's run
 * and must not be touched.
 */
final class WrittenFilesLedger
{
    /** @var array<string, true> */
    private array $paths = [];

    public function record(string $destination): void
    {
        $this->paths[$destination] = true;
    }

    public function wasWrittenThisRun(string $destination): bool
    {
        return isset($this->paths[$destination]);
    }
}
