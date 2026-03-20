<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;
use OneStopMobile\PrintNodeSdk\Exceptions\IncompletePrintJobException;
use OneStopMobile\PrintNodeSdk\Exceptions\PrintDispatchBlockedException;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\Printing\EnvironmentPrintDispatchPolicy;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchContext;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchDecision;
use OneStopMobile\PrintNodeSdk\Printing\PrintResult;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\ArrayLogger;

it('exposes pending print configuration and alternative raw helpers', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make('"1006"'),
        MockResponse::make([
            'id' => 'job-1007',
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $manager = $sdk->printing(
        defaultTitle: 'Manager title',
        defaultSource: 'Manager source',
        defaultOptions: [
            'copies' => 1,
            'duplex' => 'long-edge',
        ],
    );
    $pending = $manager->printer(62)
        ->source('Custom source')
        ->option('copies', 2)
        ->options([
            'tray' => 'rear',
        ])
        ->idempotencyKey('custom-id')
        ->contentTypeHeader('application/pdf')
        ->expireAfter('600')
        ->clientKey('client-1');

    $firstResult = $pending->rawBase64('cmF3LWJhc2U2NA==', title: 'Base64 label');
    $secondResult = $manager->printer(63)->zplBase64('WlhQTA==', title: 'ZPL Base64 label');

    expect($manager->defaultTitle())->toBe('Manager title')
        ->and($manager->defaultSource())->toBe('Manager source')
        ->and($manager->defaultOptions())->toBe([
            'copies' => 1,
            'duplex' => 'long-edge',
        ])
        ->and($pending->configuredSource())->toBe('Custom source')
        ->and($pending->optionValues())->toBe([
            'copies' => 2,
            'tray' => 'rear',
        ])
        ->and($pending->configuredIdempotencyKey())->toBe('custom-id')
        ->and($pending->configuredContentTypeHeader())->toBe('application/pdf')
        ->and($pending->configuredExpireAfter())->toBe('600')
        ->and($pending->configuredClientKey())->toBe('client-1')
        ->and($firstResult->printJobId)->toBe(1006)
        ->and($firstResult->source)->toBe('Custom source')
        ->and($secondResult->printJobId)->toBe('job-1007')
        ->and($secondResult->source)->toBe('Manager source');

    $mockClient->assertSent(function ($request, $response): bool {
        $payload = json_decode((string) $response->getPendingRequest()->body(), true, 512, JSON_THROW_ON_ERROR);
        $headers = $response->getPendingRequest()->headers()->all();

        return $payload === [
            'printerId' => 62,
            'title' => 'Base64 label',
            'contentType' => 'raw_base64',
            'content' => 'cmF3LWJhc2U2NA==',
            'source' => 'Custom source',
            'contentTypeHeader' => 'application/pdf',
            'expireAfter' => '600',
            'clientKey' => 'client-1',
            'options' => [
                'copies' => 2,
                'duplex' => 'long-edge',
                'tray' => 'rear',
            ],
        ] && $headers['X-Idempotency-Key'] === 'custom-id';
    });

    $mockClient->assertSent(function ($request, $response): bool {
        $payload = json_decode((string) $response->getPendingRequest()->body(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['printerId'] === 63
            && $payload['title'] === 'ZPL Base64 label'
            && $payload['contentType'] === 'raw_base64'
            && $payload['content'] === 'WlhQTA==';
    });
});

it('throws when a successful print response contains no recognizable identifier', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $sdk->connector()->withMockClient(new MockClient([
        MockResponse::make([
            'status' => 'queued',
        ]),
    ]));

    $sdk->printing()
        ->printer(70)
        ->pdfBase64('JVBERi0xLjQ=', title: 'Missing id');
})->throws(IncompletePrintJobException::class, 'without a recognizable print job identifier');

it('returns send when the environment policy allows the current environment', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $policy = new EnvironmentPrintDispatchPolicy(
        currentEnvironment: 'production',
        allowedEnvironments: ['production'],
    );
    $decision = $policy->decide(new PrintDispatchContext(
        pendingPrint: $sdk->printing()->printer(1),
        target: ResolvedPrintTarget::forPrinter(1),
        payload: new CreatePrintJobPayload(
            printerId: 1,
            title: 'Policy test',
            contentType: PrintContentType::PdfBase64,
            content: 'JVBERi0xLjQ=',
        ),
    ));

    expect($decision->action)->toBe(PrintDispatchAction::Send);
});

it('can skip dispatch without a logger', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $sdk->printing(
        dispatchPolicy: new class implements DecidesPrintDispatch
        {
            public function decide(PrintDispatchContext $context): PrintDispatchDecision
            {
                return PrintDispatchDecision::skip('Disabled in tests.');
            }
        },
    )->printer(80)->pdfBase64('JVBERi0xLjQ=', title: 'Skipped print');

    expect($result->wasSkipped())->toBeTrue();

    $mockClient->assertNothingSent();
});

it('can fail dispatch without a logger', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    expect(fn (): mixed => $sdk->printing(
        dispatchPolicy: new class implements DecidesPrintDispatch
        {
            public function decide(PrintDispatchContext $context): PrintDispatchDecision
            {
                return PrintDispatchDecision::fail('Disabled in tests.');
            }
        },
    )->printer(81)->pdfBase64('JVBERi0xLjQ=', title: 'Blocked print'))
        ->toThrow(PrintDispatchBlockedException::class, 'Disabled in tests.');

    $mockClient->assertNothingSent();
});

it('includes content hashes in success logs when enabled', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $logger = new ArrayLogger;

    $sdk->connector()->withMockClient(new MockClient([
        MockResponse::make([
            'id' => 2008,
        ], 200, [
            'X-Request-Id' => 'req-log-hash',
        ]),
    ]));

    $result = $sdk->printing(
        logger: $logger,
        logSuccess: true,
        includeContentHashInLogs: true,
    )->printer(82)->pdfBase64('JVBERi0xLjQ=', title: 'Logged print');

    expect($result->printJobId)->toBe(2008)
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['context'])->toMatchArray([
            'printerId' => 82,
            'title' => 'Logged print',
            'contentHash' => sha1('JVBERi0xLjQ='),
            'contentLength' => strlen('JVBERi0xLjQ='),
        ]);
});

it('throws when sending without content', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    expect(fn (): PrintResult => $sdk->printing()->printer(83)->send())
        ->toThrow(IncompletePrintJobException::class, 'A print job requires content before it can be sent.');
});

it('validates incomplete pending prints before dispatch', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $manager = $sdk->printing();

    expect(fn () => $manager->ensureReady($manager->printer(99)))
        ->toThrow(IncompletePrintJobException::class, 'A print job requires content before it can be sent.');
});
