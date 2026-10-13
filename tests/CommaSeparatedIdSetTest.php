<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;

it('normalizes identifier sets from strings and arrays', function (): void {
    $fromString = CommaSeparatedIdSet::from('1, 2, 2');
    $fromArray = CommaSeparatedIdSet::from(['5', 6, ' 7 ']);

    expect($fromString->ids)->toBe([1, 2])
        ->and((string) $fromString)->toBe('1,2')
        ->and($fromArray->ids)->toBe([5, 6, 7])
        ->and((string) $fromArray)->toBe('5,6,7');
});

it('rejects empty identifier sets', function (): void {
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from(''))
        ->toThrow(InvalidIdentifierSetException::class, 'The identifier set must contain at least one identifier.');
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from([]))
        ->toThrow(InvalidIdentifierSetException::class, 'The identifier set must contain at least one identifier.');
});

it('rejects the whole set when one part is not an id', function (int|array|string $set, string $message): void {
    expect(fn (): CommaSeparatedIdSet => CommaSeparatedIdSet::from($set))
        ->toThrow(InvalidIdentifierSetException::class, $message);
})->with([
    'a word between ids' => ['12,abc', '"abc" is not a PrintNode id.'],
    'a decimal' => ['1.9', '"1.9" is not a PrintNode id.'],
    'scientific notation' => ['1e3', '"1e3" is not a PrintNode id.'],
    'a negative number' => ['-5', '"-5" is not a PrintNode id.'],
    'a trailing comma' => ['1,2,', '"" is not a PrintNode id.'],
    'zero' => [0, '0 is not a PrintNode id.'],
    'a float in an array' => [[1, 1.5], '1.5 is not a PrintNode id.'],
    'an object in an array' => [[1, new stdClass], 'stdClass is not a PrintNode id.'],
]);
