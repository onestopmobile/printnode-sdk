<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Exceptions\PrintDispatchBlockedException;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchContext;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchDecision;
use OneStopMobile\PrintNodeSdk\Printing\PrintManagerConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\ArrayLogger;

it('dispatches a print job directly to a printer id', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('987', 200, [
            'X-Request-Id' => 'req-print-1',
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $sdk->printing(
        defaultTitle: 'Fallback title',
        defaultSource: 'App Printer',
        defaultOptions: ['copies' => 2],
        defaultIdempotencyPrefix: 'prints',
    )->printer(42)
        ->option('duplex', 'long-edge')
        ->pdfUrl('https://example.com/packing-slip.pdf');

    expect($result->printJobId)->toBe(987)
        ->and($result->printNodePrinterId)->toBe(42)
        ->and($result->title)->toBe('Fallback title')
        ->and($result->source)->toBe('App Printer')
        ->and($result->requestId)->toBe('req-print-1')
        ->and($result->idempotencyKey)->toStartWith('prints-')
        ->and($result->wasSent())->toBeTrue()
        ->and($result->options)->toBe([
            'copies' => 2,
            'duplex' => 'long-edge',
        ]);

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();
        $headers = $pendingRequest->headers()->all();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/printjobs'
            && isset($headers['X-Idempotency-Key'])
            && json_decode((string) $pendingRequest->body(), true, 512, JSON_THROW_ON_ERROR) === [
                'printerId' => 42,
                'title' => 'Fallback title',
                'contentType' => 'pdf_uri',
                'content' => 'https://example.com/packing-slip.pdf',
                'source' => 'App Printer',
                'options' => [
                    'copies' => 2,
                    'duplex' => 'long-edge',
                ],
            ];
    });
});

it('supports configuring the print manager through a config object', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('988'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $sdk->printingWithConfig(new PrintManagerConfig(
        defaultTitle: 'Configured title',
        defaultSource: 'Configured source',
    ))->printer(43)->pdfUrl('https://example.com/configured.pdf');

    expect($result->printJobId)->toBe(988)
        ->and($result->title)->toBe('Configured title')
        ->and($result->source)->toBe('Configured source');
});

it('encodes raw content before dispatching', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('1001'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $sdk->printing()
        ->printer(55)
        ->title('Shipping label')
        ->raw('^XA^FO50,50^FDhello^FS^XZ');

    $mockClient->assertSent(function ($request, $response): bool {
        $payload = json_decode((string) $response->getPendingRequest()->body(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['contentType'] === 'raw_base64'
            && $payload['content'] === base64_encode('^XA^FO50,50^FDhello^FS^XZ');
    });
});

it('provides semantic zpl helpers on top of raw printing', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('1002'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $sdk->printing()
        ->printer(56)
        ->zpl('^XA^FO50,50^FDzpl^FS^XZ', title: 'ZPL label');

    $mockClient->assertSent(function ($request, $response): bool {
        $payload = json_decode((string) $response->getPendingRequest()->body(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['title'] === 'ZPL label'
            && $payload['contentType'] === 'raw_base64'
            && $payload['content'] === base64_encode('^XA^FO50,50^FDzpl^FS^XZ');
    });
});

it('omits an empty idempotency key header on print manager dispatches', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('1003'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $sdk->printing()
        ->printer(57)
        ->idempotencyKey('')
        ->pdfUrl('https://example.com/no-idempotency.pdf');

    $mockClient->assertSent(function ($request, $response): bool {
        $headers = $response->getPendingRequest()->headers()->all();

        return ! array_key_exists('X-Idempotency-Key', $headers);
    });
});

it('accepts nested response payloads containing a print job id', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make([
            'data' => [
                'id' => 4444,
            ],
        ], 200, [
            'X-Request-Id' => 'req-print-nested',
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $sdk->printing()
        ->printer(58)
        ->pdfUrl('https://example.com/nested.pdf');

    expect($result->printJobId)->toBe(4444)
        ->and($result->requestId)->toBe('req-print-nested');
});

it('can skip dispatch through a configured print policy', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);
    $logger = new ArrayLogger;

    $sdk->connector()->withMockClient($mockClient);

    $result = $sdk->printing(
        dispatchPolicy: new class implements DecidesPrintDispatch
        {
            public function decide(PrintDispatchContext $context): PrintDispatchDecision
            {
                return PrintDispatchDecision::skip('Printing is disabled outside production.');
            }
        },
        logger: $logger,
    )->printer(77)->pdfUrl('https://example.com/label.pdf', title: 'Skipped label');

    expect($result->printJobId)->toBeNull()
        ->and($result->wasSkipped())->toBeTrue()
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('info')
        ->and($logger->records[0]['context'])->toMatchArray([
            'printerId' => 77,
            'title' => 'Skipped label',
            'reason' => 'Printing is disabled outside production.',
        ]);

    $mockClient->assertNothingSent();
});

it('can fail dispatch through a configured print policy', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);
    $logger = new ArrayLogger;

    $sdk->connector()->withMockClient($mockClient);

    $sdk->printing(
        dispatchPolicy: new class implements DecidesPrintDispatch
        {
            public function decide(PrintDispatchContext $context): PrintDispatchDecision
            {
                return PrintDispatchDecision::fail('Physical printing is blocked in this environment.');
            }
        },
        logger: $logger,
    )->printer(88)->pdfUrl('https://example.com/blocked.pdf');
})->throws(PrintDispatchBlockedException::class, 'Physical printing is blocked in this environment.');

it('logs successful and failed dispatches when enabled', function (): void {
    $successSdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $failureSdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $successLogger = new ArrayLogger;
    $failureLogger = new ArrayLogger;

    $successSdk->connector()->withMockClient(new MockClient([
        MockResponse::make('2003', 200, [
            'X-Request-Id' => 'req-print-2003',
        ]),
    ]));
    $failureSdk->connector()->withMockClient(new MockClient([
        MockResponse::make(['message' => 'boom'], 500),
    ]));

    $successResult = $successSdk->printing(
        logger: $successLogger,
        logSuccess: true,
    )->printer(90)->pdfBase64('JVBERi0xLjQ=', title: 'Successful print');

    expect($successResult->wasSent())->toBeTrue()
        ->and($successLogger->records)->toHaveCount(1)
        ->and($successLogger->records[0]['level'])->toBe('info')
        ->and($successLogger->records[0]['context'])->toMatchArray([
            'printerId' => 90,
            'title' => 'Successful print',
            'requestId' => 'req-print-2003',
        ]);

    expect(fn (): PrintResult => $failureSdk->printing(
        logger: $failureLogger,
    )->printer(91)->pdfBase64('JVBERi0xLjQ=', title: 'Failed print'))
        ->toThrow(ApiErrorException::class, 'boom');

    expect($failureLogger->records)->toHaveCount(1)
        ->and($failureLogger->records[0]['level'])->toBe('error')
        ->and($failureLogger->records[0]['context'])->toMatchArray([
            'printerId' => 91,
            'title' => 'Failed print',
            'exception' => ApiErrorException::class,
        ]);
});
