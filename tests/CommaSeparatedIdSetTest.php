<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;

it('normalizes identifier sets from strings and arrays', function (): void {
    $fromString = CommaSeparatedIdSet::from('1, 2, invalid, 2');
    $fromArray = CommaSeparatedIdSet::from(['5', 6, ' 7 ']);

    expect($fromString->ids)->toBe([1, 2])
        ->and((string) $fromString)->toBe('1,2')
        ->and($fromArray->ids)->toBe([5, 6, 7])
        ->and((string) $fromArray)->toBe('5,6,7');
});

it('rejects empty or invalid identifier sets', function (): void {
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from(''))->toThrow(InvalidIdentifierSetException::class);
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from([]))->toThrow(InvalidIdentifierSetException::class);
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from(['invalid']))->toThrow(InvalidIdentifierSetException::class);
});
