<?php

declare(strict_types=1);

use Lightitlabs\Tools\WrittenFilesLedger;

describe('WrittenFilesLedger', function (): void {
    it('does not know about a path until it is recorded', function (): void {
        $ledger = new WrittenFilesLedger;

        expect($ledger->wasWrittenThisRun('/tmp/LoginAction.php'))->toBeFalse();
    });

    it('remembers a path once recorded', function (): void {
        $ledger = new WrittenFilesLedger;

        $ledger->record('/tmp/LoginAction.php');

        expect($ledger->wasWrittenThisRun('/tmp/LoginAction.php'))->toBeTrue()
            ->and($ledger->wasWrittenThisRun('/tmp/OtherFile.php'))->toBeFalse();
    });

    it('is independent per instance', function (): void {
        $first = new WrittenFilesLedger;
        $second = new WrittenFilesLedger;

        $first->record('/tmp/LoginAction.php');

        expect($second->wasWrittenThisRun('/tmp/LoginAction.php'))->toBeFalse();
    });
});
