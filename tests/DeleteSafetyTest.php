<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function deletingSdk(MockClient $mockClient): PrintNodeSdk
{
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $sdk->connector()->withMockClient($mockClient);

    return $sdk;
}

it('only deletes everything when asked for by name', function (): void {
    $mockClient = new MockClient([
        MockResponse::make([10, 11]),
        MockResponse::make([12]),
        MockResponse::make([13, 14]),
    ]);
    $sdk = deletingSdk($mockClient);

    expect($sdk->printJobs()->deleteAll())->toBe([10, 11])
        ->and($sdk->printJobs()->deleteByPrinters(5, 12))->toBe([12])
        ->and($sdk->printJobs()->deleteAllByPrinters([5, 6]))->toBe([13, 14]);

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printjobs');
    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printers/5/printjobs/12');
    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printers/5,6/printjobs');
});

it('requires ids for a regular delete', function (): void {
    $sdk = deletingSdk(new MockClient([]));
    $printNodeComputerId = null;

    // Before, delete(null) sent DELETE /computers and removed every computer of the account.
    expect(fn (): array => $sdk->computers()->delete($printNodeComputerId))->toThrow(TypeError::class);
});

it('sends nothing when an id in a delete has a typo', function (): void {
    $mockClient = new MockClient([MockResponse::make([12])]);
    $sdk = deletingSdk($mockClient);

    expect(fn (): array => $sdk->printJobs()->delete('12,l3'))
        ->toThrow(InvalidIdentifierSetException::class, '"l3" is not a PrintNode id.');
    $mockClient->assertNothingSent();
});
