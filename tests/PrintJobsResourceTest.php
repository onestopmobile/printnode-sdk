<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\Pagination;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('creates and scopes print jobs through the sdk', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('1234'),
        MockResponse::make([
            ['id' => 10, 'state' => 'queued'],
            ['id' => 11, 'state' => 'done'],
        ]),
        MockResponse::make([
            [
                ['state' => 'queued'],
                ['state' => 'done'],
            ],
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $createdId = $sdk->printJobs()->create(
        new CreatePrintJobPayload(
            printerId: 5,
            title: 'Invoice',
            contentType: PrintContentType::PdfUri,
            content: 'https://example.com/invoice.pdf',
        ),
        idempotencyKey: 'job-123',
    );

    $jobs = $sdk->printJobs()->byPrinters(5, [10, 11]);
    $states = $sdk->printJobs()->states(pagination: new Pagination(limit: 1));

    expect($createdId)->toBe(1234)
        ->and($jobs)->toHaveCount(2)
        ->and($jobs[0]->id)->toBe(10)
        ->and($states[0][0]->state)->toBe('queued');

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/printjobs'
            && $pendingRequest->headers()->all()['X-Idempotency-Key'] === 'job-123'
            && json_decode((string) $pendingRequest->body(), true, 512, JSON_THROW_ON_ERROR) === [
                'printerId' => 5,
                'title' => 'Invoice',
                'contentType' => 'pdf_uri',
                'content' => 'https://example.com/invoice.pdf',
                'source' => 'PrintNode SDK',
            ];
    });
});

it('omits an empty idempotency key header on direct resource calls', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make('1234'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $sdk->printJobs()->create(
        new CreatePrintJobPayload(
            printerId: 5,
            title: 'Invoice',
            contentType: PrintContentType::PdfUri,
            content: 'https://example.com/invoice.pdf',
        ),
        idempotencyKey: '',
    );

    $mockClient->assertSent(function ($request, $response): bool {
        $headers = $response->getPendingRequest()->headers()->all();

        return ! array_key_exists('X-Idempotency-Key', $headers);
    });
});

it('accepts nested response payloads on direct resource calls', function (): void {
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

    $createdId = $sdk->printJobs()->create(
        new CreatePrintJobPayload(
            printerId: 5,
            title: 'Nested response job',
            contentType: PrintContentType::PdfUri,
            content: 'https://example.com/nested.pdf',
        ),
    );

    expect($createdId)->toBe(4444);
});
