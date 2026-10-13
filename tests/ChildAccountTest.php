<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Payloads\CreateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

/**
 * An SDK that works inside the warehouse child account unless a call says otherwise.
 */
function sdkWithDefaultChildAccount(): PrintNodeSdk
{
    return new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
        defaultChildAccount: ChildAccountContext::byEmail('warehouse@example.com'),
    ));
}

/**
 * @return array<string, string>
 */
function sentChildAccountHeaders(MockClient $mockClient): array
{
    $headers = $mockClient->getLastPendingRequest()?->headers()->all() ?? [];

    return array_intersect_key($headers, array_flip(ChildAccountContext::HEADER_NAMES));
}

it('sends only the child account the request asks for', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make([])]);
    $sdk->connector()->withMockClient($mockClient);

    $sdk->printers()->all(childAccount: ChildAccountContext::byCreatorRef('customer-12'));

    expect(sentChildAccountHeaders($mockClient))->toBe([
        'X-Child-Account-By-CreatorRef' => 'customer-12',
    ]);
});

it('falls back to the default child account when the request does not name one', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make([])]);
    $sdk->connector()->withMockClient($mockClient);

    $sdk->printers()->all();

    expect(sentChildAccountHeaders($mockClient))->toBe([
        'X-Child-Account-By-Email' => 'warehouse@example.com',
    ]);
});

it('creates child accounts as the integrator account', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make(['Account' => ['id' => 301]])]);
    $sdk->connector()->withMockClient($mockClient);

    $sdk->account()->create(new CreateChildAccountPayload(
        email: 'customer-12@example.com',
        password: 'secret-password',
        creatorRef: 'customer-12',
    ));

    expect(sentChildAccountHeaders($mockClient))->toBe([]);
});

it('describes the integrator account without any child account header', function (): void {
    $parent = ChildAccountContext::parentAccount();

    expect($parent->isParentAccount())->toBeTrue()
        ->and($parent->toHeaders())->toBe([])
        ->and(ChildAccountContext::byId(12)->isParentAccount())->toBeFalse();
});

it('refuses to delete the integrator account', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make([])]);
    $sdk->connector()->withMockClient($mockClient);

    expect(fn () => $sdk->account()->delete(ChildAccountContext::parentAccount()))
        ->toThrow(InvalidArgumentException::class, 'Pass the child account to delete.');
    $mockClient->assertNothingSent();
});

it('leaves requests that were not built by the SDK alone', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make([])]);
    $sdk->connector()->withMockClient($mockClient);

    $sdk->connector()->send(new class extends Request
    {
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return '/whoami';
        }
    });

    expect(sentChildAccountHeaders($mockClient))->toBe([
        'X-Child-Account-By-Email' => 'warehouse@example.com',
    ]);
});

it('prints from the child account that owns the resolved printer', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make('987')]);
    $sdk->connector()->withMockClient($mockClient);

    $resolver = new class implements ResolvesPrintTarget
    {
        public function resolve(mixed $target): ResolvedPrintTarget
        {
            return new ResolvedPrintTarget(
                printNodePrinterId: 42,
                childAccount: ChildAccountContext::byCreatorRef('customer-12'),
            );
        }
    };

    $sdk->printing(targetResolver: $resolver)
        ->to('customer-12-printer')
        ->raw('receipt', title: 'Receipt 1001');

    expect(sentChildAccountHeaders($mockClient))->toBe([
        'X-Child-Account-By-CreatorRef' => 'customer-12',
    ]);
});

it('lets a print name its own child account', function (): void {
    $sdk = sdkWithDefaultChildAccount();
    $mockClient = new MockClient([MockResponse::make('987')]);
    $sdk->connector()->withMockClient($mockClient);

    $pendingPrint = $sdk->printing()
        ->printer(42)
        ->childAccount(ChildAccountContext::byId(12));

    $pendingPrint->raw('receipt', title: 'Receipt 1001');

    expect($pendingPrint->configuredChildAccount()?->value)->toBe('12')
        ->and(sentChildAccountHeaders($mockClient))->toBe([
            'X-Child-Account-By-Id' => '12',
        ]);
});
