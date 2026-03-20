<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;

it('reuses existing identifier sets and ignores non-scalar array items', function (): void {
    $existing = CommaSeparatedIdSet::from([1, new stdClass, '2']);
    $resolved = CommaSeparatedIdSet::from($existing);

    expect($existing->ids)->toBe([1, 2])
        ->and($resolved)->toBe($existing)
        ->and($resolved->isEmpty())->toBeFalse();
});
