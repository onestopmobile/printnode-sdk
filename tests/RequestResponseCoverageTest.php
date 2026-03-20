<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\FormEndpointRequest;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

it('sends form endpoint requests with filtered query parameters and headers', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make([
            'ok' => true,
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $response = $sdk->connector()->send(new FormEndpointRequest(
        Method::POST,
        '/form/submit',
        [
            'alpha' => 'beta',
        ],
        ChildAccountContext::byCreatorRef('warehouse-ref'),
        [
            'limit' => 1,
            'after' => null,
        ],
        [
            'X-Test' => 'yes',
        ],
    ));
    $pendingRequest = $mockClient->getLastResponse()?->getPendingRequest();

    expect($response->dtoOrThrow())->toBe([
        'ok' => true,
    ])
        ->and($pendingRequest?->getUrl())->toBe('https://api.printnode.test/form/submit')
        ->and($pendingRequest?->getRequest()->query()->all())->toBe([
            'limit' => 1,
        ])
        ->and((string) $pendingRequest?->body())->toBe('alpha=beta')
        ->and($pendingRequest?->headers()->all())->toMatchArray([
            'X-Child-Account-By-CreatorRef' => 'warehouse-ref',
            'X-Test' => 'yes',
        ]);
});

it('falls back to generic Saloon json responses for dto conversion', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $request = new EndpointRequest(Method::GET, '/generic');
    $pendingRequest = new PendingRequest($sdk->connector(), $request);
    $response = new Response(
        new PsrResponse(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
        $pendingRequest,
        new PsrRequest('GET', 'https://api.printnode.test/generic'),
    );

    expect($request->createDtoFromResponse($response))->toBe([
        'ok' => true,
    ]);
});

it('exposes response content types and default api error messages', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'invalid-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        'https://api.printnode.test/whoami' => MockResponse::make('gateway broke', 502, [
            'Content-Type' => 'text/plain',
        ]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    try {
        $sdk->whoAmI();
    } catch (ApiErrorException $apiErrorException) {
        expect($apiErrorException->getMessage())->toBe('PrintNode API request failed with HTTP 502.')
            ->and($mockClient->getLastResponse()?->contentType())->toBe('text/plain');

        return;
    }

    test()->fail('Expected a generic API exception to be thrown.');
});
