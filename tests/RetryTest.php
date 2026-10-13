<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Exceptions\RateLimitException;
use OneStopMobile\PrintNodeSdk\Exceptions\ValidationException;
use OneStopMobile\PrintNodeSdk\Laravel\LaravelPrintNodeConfigFactory;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function retryingSdk(int $tries = 3): PrintNodeSdk
{
    return new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
        tries: $tries,
    ));
}

function receiptPayload(): CreatePrintJobPayload
{
    return new CreatePrintJobPayload(
        printerId: 42,
        title: 'Receipt 1001',
        contentType: PrintContentType::RawBase64,
        content: base64_encode('receipt'),
    );
}

function connectionLost(): MockResponse
{
    return MockResponse::make()->throw(
        fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(
            new ConnectException('Connection timed out', new PsrRequest('GET', 'https://api.printnode.test')),
            $pendingRequest,
        ),
    );
}

it('retries a read after a server error', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Service unavailable'], 503),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    $printers = $sdk->printers()->all();

    expect($printers[0]->id)->toBe(42);
    $mockClient->assertSentCount(2);
});

it('retries a read when the connection drops', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        connectionLost(),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    // The printer only comes from the second mock response, so getting it back proves the retry.
    expect($sdk->printers()->all()[0]->id)->toBe(42);
});

it('retries a print job that carries an idempotency key', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Bad gateway'], 502),
        MockResponse::make('987'),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    expect($sdk->printJobs()->create(receiptPayload(), idempotencyKey: 'receipt-1001'))->toBe(987);
    $mockClient->assertSentCount(2);
});

it('never repeats a print job without an idempotency key', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Bad gateway'], 502),
        MockResponse::make('987'),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    expect(fn (): int|string => $sdk->printJobs()->create(receiptPayload()))
        ->toThrow(ApiErrorException::class, 'Bad gateway');
    $mockClient->assertSentCount(1);
});

it('does not retry a request PrintNode rejected', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Invalid printer set'], 400),
        MockResponse::make([]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    expect(fn (): array => $sdk->printers()->all())
        ->toThrow(ValidationException::class, 'Invalid printer set');
    $mockClient->assertSentCount(1);
});

it('throws the SDK exception once every attempt is used up', function (): void {
    $sdk = retryingSdk(tries: 2);
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => '30']),
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => '30']),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    try {
        $sdk->printers()->all();
        $this->fail('Expected a RateLimitException.');
    } catch (RateLimitException $rateLimitException) {
        expect($rateLimitException->retryAfter)->toBe('30');
    }

    $mockClient->assertSentCount(2);
});

it('turns the retry interval from the Laravel config into milliseconds for Saloon', function (): void {
    $config = (new LaravelPrintNodeConfigFactory)->makePrintNodeConfig(fakeConfigRepository([
        'printnode' => [
            'tries' => 2,
            'retry_interval' => 1.5,
        ],
    ]));

    // Saloon's HasTries::$retryInterval is in milliseconds.
    expect($config->retryInterval)->toBe(1500)
        ->and(new PrintNodeSdk($config)->connector()->retryInterval)->toBe(1500);
});
