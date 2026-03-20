<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\Pagination;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('lists computers via the SDK resource', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make([
            [
                'id' => 575407,
                'name' => 'DESKTOP-N7QV08T',
                'hostname' => 'Magazijn2@WERKPLEK-6',
                'state' => 'connected',
            ],
            [
                'id' => 609529,
                'name' => 'WERKPLEK-1',
                'hostname' => 'Magazijn 2@WERKPLEK-1',
                'state' => 'connected',
            ],
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $computers = $sdk->computers()->all(new Pagination(limit: 2, after: 10));

    expect($computers)->toHaveCount(2)
        ->and($computers[0]->id)->toBe(575407)
        ->and($computers[0]->hostname)->toBe('Magazijn2@WERKPLEK-6')
        ->and($computers[0]->state)->toBe('connected')
        ->and($computers[1]->id)->toBe(609529);

    $lastResponse = $mockClient->getLastResponse();
    $url = $lastResponse?->getPendingRequest()->getUrl();
    $query = $lastResponse?->getPendingRequest()->getRequest()->query()->all();

    expect($url)->toStartWith('https://api.printnode.test/computers')
        ->and($query)->toBe([
            'limit' => 2,
            'after' => 10,
        ]);
});

it('lists printers by computer set', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make([
            [
                'id' => 42,
                'name' => 'Brother',
                'computer' => ['id' => 12],
            ],
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $printers = $sdk->printers()->all(printerSet: 42, computerSet: [12, 13]);

    expect($printers)->toHaveCount(1)
        ->and($printers[0]->id)->toBe(42)
        ->and($printers[0]->computerId)->toBe(12);

    expect($mockClient->getLastResponse()?->getPendingRequest()->getUrl())
        ->toBe('https://api.printnode.test/computers/12,13/printers/42');
});

it('rejects empty computer identifier sets before sending a request', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make([]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    expect(fn (): array => $sdk->computers()->get(''))->toThrow(InvalidIdentifierSetException::class);

    $mockClient->assertNothingSent();
});
