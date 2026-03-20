<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Exceptions\UnresolvablePrintTargetException;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('dispatches through a user-defined print target resolver', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('2002'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $resolver = new class implements ResolvesPrintTarget
    {
        public function resolve(mixed $target): ResolvedPrintTarget
        {
            expect($target)->toBe('packing-slips');

            return new ResolvedPrintTarget(
                printNodePrinterId: 77,
                source: 'Resolved source',
                options: ['tray' => 'A4'],
            );
        }
    };

    $result = $sdk->printing(targetResolver: $resolver)
        ->to('packing-slips')
        ->title('Pakbon 1001')
        ->option('copies', 3)
        ->pdfBase64('JVBERi0xLjQ=');

    expect($result->printJobId)->toBe(2002)
        ->and($result->printNodePrinterId)->toBe(77)
        ->and($result->source)->toBe('Resolved source')
        ->and($result->options)->toBe([
            'tray' => 'A4',
            'copies' => 3,
        ]);
});

it('supports to as a semantic alias for target resolution', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('2004'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $resolver = new class implements ResolvesPrintTarget
    {
        public function resolve(mixed $target): ResolvedPrintTarget
        {
            expect($target)->toBe('labels');

            return new ResolvedPrintTarget(
                printNodePrinterId: 78,
                source: 'Resolved alias source',
            );
        }
    };

    $result = $sdk->printing(targetResolver: $resolver)
        ->to('labels')
        ->pdfUrl('https://example.com/label.pdf');

    expect($result->printJobId)->toBe(2004)
        ->and($result->printNodePrinterId)->toBe(78)
        ->and($result->source)->toBe('Resolved alias source');
});

it('throws a clear exception when target resolution is used without a resolver', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $sdk->printing()
        ->to('labels')
        ->pdfUrl('https://example.com/label.pdf');
})->throws(UnresolvablePrintTargetException::class, 'No print target resolver is configured');
