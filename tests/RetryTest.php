<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Exceptions\ConnectionException;
use OneStopMobile\PrintNodeSdk\Exceptions\PrintNodeException;
use OneStopMobile\PrintNodeSdk\Exceptions\RateLimitException;
use OneStopMobile\PrintNodeSdk\Exceptions\ValidationException;
use OneStopMobile\PrintNodeSdk\Http\PrintNodeConnector;
use OneStopMobile\PrintNodeSdk\Laravel\LaravelPrintNodeConfigFactory;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\Printing\PrintResult;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Config;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * The waits the connector asked for, in microseconds. Nothing really sleeps in these tests.
 *
 * @return list<int>
 */
function sleptMicroseconds(): array
{
    // Saloon also calls the sleep handler for the configured interval, which is 0 here.
    return array_values(array_filter(
        $GLOBALS['sleptMicroseconds'] ?? [],
        static fn (int $microseconds): bool => $microseconds > 0,
    ));
}

beforeEach(function (): void {
    $GLOBALS['sleptMicroseconds'] = [];
    Config::sleepUsing(static function (int $microseconds): void {
        $GLOBALS['sleptMicroseconds'][] = $microseconds;
    });
});

afterEach(function (): void {
    Config::sleepUsing(null);
});

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
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => '3']),
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => '3']),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    try {
        $sdk->printers()->all();
        $this->fail('Expected a RateLimitException.');
    } catch (RateLimitException $rateLimitException) {
        expect($rateLimitException->retryAfter)->toBe('3');
    }

    $mockClient->assertSentCount(2);
});

it('waits as long as Retry-After asks before trying again', function (string $retryAfter, int $seconds): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => $retryAfter]),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    expect($sdk->printers()->all()[0]->id)->toBe(42)
        ->and(sleptMicroseconds())->toBe($seconds === 0 ? [] : [$seconds * 1_000_000]);
})->with([
    'seconds' => ['4', 4],
    'a date in the past' => ['Wed, 21 Oct 2015 07:28:00 GMT', 0],

    'something else' => ['soon', PrintNodeConnector::DEFAULT_RATE_LIMIT_WAIT_SECONDS],
]);

it('waits a second after a 429 without Retry-After', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Slow down'], 429),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    expect($sdk->printers()->all()[0]->id)->toBe(42)
        ->and(sleptMicroseconds())->toBe([1_000_000]);
});

it('waits for an HTTP date in Retry-After', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 5)]),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    $sdk->printers()->all();

    expect(sleptMicroseconds())->toHaveCount(1)
        ->and(sleptMicroseconds()[0])->toBeGreaterThanOrEqual(4_000_000)->toBeLessThanOrEqual(5_000_000);
});

it('stops trying when Retry-After is longer than it waits for itself', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([
        MockResponse::make(['message' => 'Slow down'], 429, ['Retry-After' => '30']),
        MockResponse::make([['id' => 42, 'name' => 'Receipt printer']]),
    ]);
    $sdk->connector()->withMockClient($mockClient);

    try {
        $sdk->printers()->all();
        $this->fail('Expected a RateLimitException.');
    } catch (RateLimitException $rateLimitException) {
        // The caller (a queued job, say) can come back after Retry-After instead of holding a worker.
        expect($rateLimitException->retryAfter)->toBe('30');
    }

    $mockClient->assertSentCount(1);
    expect(sleptMicroseconds())->toBe([]);
});

it('throws a ConnectionException when the last attempt cannot reach PrintNode', function (): void {
    $sdk = retryingSdk(tries: 2);
    $mockClient = new MockClient([connectionLost(), connectionLost()]);
    $sdk->connector()->withMockClient($mockClient);

    expect(fn (): array => $sdk->printers()->all())
        ->toThrow(ConnectionException::class, 'Could not reach PrintNode: Connection timed out');
});

it('throws a ConnectionException for a print job whose connection drops', function (): void {
    $sdk = retryingSdk();
    $mockClient = new MockClient([connectionLost()]);
    $sdk->connector()->withMockClient($mockClient);

    try {
        // Without a key the job is never sent twice, so the first failure is the last.
        $sdk->printJobs()->create(receiptPayload());
        $this->fail('Expected a ConnectionException.');
    } catch (ConnectionException $connectionException) {
        expect($connectionException)->toBeInstanceOf(PrintNodeException::class)
            ->and($connectionException->getPrevious())->toBeInstanceOf(FatalRequestException::class);
    }
});

it('throws a ConnectionException from the print manager too', function (): void {
    $sdk = retryingSdk(tries: 1);
    $sdk->connector()->withMockClient(new MockClient([connectionLost()]));

    expect(fn (): PrintResult => $sdk->printing()->printer(42)->idempotencyKey('receipt-1001')->raw('receipt'))
        ->toThrow(ConnectionException::class);
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
