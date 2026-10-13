<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Exceptions\ConflictException;
use OneStopMobile\PrintNodeSdk\Exceptions\DuplicatePrintJobException;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\ArrayLogger;

function idempotencySdk(MockClient $mockClient): PrintNodeSdk
{
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $sdk->connector()->withMockClient($mockClient);

    return $sdk;
}

/**
 * What PrintNode answers when an idempotency key was already used in the last 24 hours.
 */
function keyCollision(string $key): MockResponse
{
    return MockResponse::make([
        'code' => 'Conflict',
        'message' => 'Idempotency key collision: '.$key,
    ], 409, ['X-Request-Id' => 'req-collision']);
}

it('reports a reused idempotency key as a duplicate print job', function (): void {
    $sdk = idempotencySdk(new MockClient([keyCollision('receipt-1001')]));

    try {
        $sdk->printJobs()->create(new CreatePrintJobPayload(
            printerId: 42,
            title: 'Receipt 1001',
            contentType: PrintContentType::RawBase64,
            content: base64_encode('receipt'),
        ), idempotencyKey: 'receipt-1001');
        $this->fail('Expected a DuplicatePrintJobException.');
    } catch (DuplicatePrintJobException $duplicatePrintJobException) {
        expect($duplicatePrintJobException)->toBeInstanceOf(ConflictException::class)
            ->and($duplicatePrintJobException->idempotencyKey)->toBe('receipt-1001')
            ->and($duplicatePrintJobException->status)->toBe(409)
            ->and($duplicatePrintJobException->requestId)->toBe('req-collision')
            ->and($duplicatePrintJobException->getMessage())->toBe('PrintNode already accepted a print job with idempotency key "receipt-1001".');
    }
});

it('keeps a conflict without an idempotency key a plain conflict', function (): void {
    $sdk = idempotencySdk(new MockClient([keyCollision('unknown')]));

    expect(fn (): int|string => $sdk->printJobs()->create(new CreatePrintJobPayload(
        printerId: 42,
        title: 'Receipt 1001',
        contentType: PrintContentType::RawBase64,
        content: base64_encode('receipt'),
    )))->toThrow(fn (ConflictException $conflict) => expect($conflict)->not->toBeInstanceOf(DuplicatePrintJobException::class));
});

it('treats a print that already reached PrintNode as done, not failed', function (): void {
    $logger = new ArrayLogger;
    $sdk = idempotencySdk(new MockClient([keyCollision('receipt-1001')]));

    $result = $sdk->printing(logger: $logger, logSuccess: true)
        ->printer(42)
        ->idempotencyKey('receipt-1001')
        ->raw('receipt', title: 'Receipt 1001');

    expect($result->wasAlreadySent())->toBeTrue()
        ->and($result->wasSent())->toBeFalse()
        ->and($result->printJobId)->toBeNull()
        ->and($result->requestId)->toBe('req-collision')
        ->and($result->idempotencyKey)->toBe('receipt-1001')
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('info')
        ->and($logger->records[0]['message'])->toBe('PrintNode print job was already sent with this idempotency key.');
});

it('sends identical prints without a key every time', function (): void {
    $mockClient = new MockClient([
        MockResponse::make('987'),
        MockResponse::make('988'),
    ]);
    $printing = idempotencySdk($mockClient)->printing(defaultIdempotencyPrefix: 'my-app');
    $drawerKick = "\x1Bp\x00\x19\xFA";

    $first = $printing->printer(42)->raw($drawerKick, title: 'Open drawer');
    $second = $printing->printer(42)->raw($drawerKick, title: 'Open drawer');

    expect($first->printJobId)->toBe(987)
        ->and($second->printJobId)->toBe(988)
        ->and($second->idempotencyKey)->toBeNull();

    $mockClient->assertSentCount(2);
    $mockClient->assertNotSent(
        fn ($request, $response): bool => array_key_exists('X-Idempotency-Key', $response->getPendingRequest()->headers()->all()),
    );
});
