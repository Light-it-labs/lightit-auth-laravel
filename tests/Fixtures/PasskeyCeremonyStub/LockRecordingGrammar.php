<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * SQLite has no row locks, so `lockForUpdate()` compiles to nothing there; this
 * grammar records which tables a query asked to lock before compiling as usual.
 */
final class LockRecordingGrammar extends SQLiteGrammar
{
    /**
     * @var list<string>
     */
    public array $lockedForUpdate = [];

    protected function compileLock(Builder $query, $value): string
    {
        if ($value === true && \is_string($query->from)) {
            $this->lockedForUpdate[] = $query->from;
        }

        return parent::compileLock($query, $value);
    }
}
