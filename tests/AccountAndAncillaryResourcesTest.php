<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Enums\AccountState;
use OneStopMobile\PrintNodeSdk\Enums\OperatingSystem;
use OneStopMobile\PrintNodeSdk\Payloads\DownloadClientPatchPayload;
use OneStopMobile\PrintNodeSdk\Payloads\WebhookPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('handles account, download, scale, webhook and misc endpoints', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $mockClient = new MockClient([
        MockResponse::make([
            'id' => 99,
            'email' => 'hello@example.com',
        ]),
        MockResponse::make('active'),
        MockResponse::make([
            'ok' => true,
        ]),
        MockResponse::make('laser'),
        MockResponse::make('api-key-value'),
        MockResponse::make([
            'clientKey' => 'client-123',
        ]),
        MockResponse::make([
            'id' => 18,
            'os' => 'windows',
        ]),
        MockResponse::make([
            'updated' => true,
        ]),
        MockResponse::make([
            ['computer' => 1, 'deviceName' => 'Scale A'],
        ]),
        MockResponse::make([
            ['computer' => 1, 'deviceName' => 'Scale A'],
        ]),
        MockResponse::make([
            'computer' => 1,
            'deviceName' => 'Scale A',
            'deviceNum' => 0,
        ]),
        MockResponse::make([
            'ok' => true,
        ]),
        MockResponse::make([
            ['id' => 188, 'url' => 'https://example.com/hook'],
        ]),
        MockResponse::make([
            'id' => 188,
            'url' => 'https://example.com/hook',
        ]),
        MockResponse::make([
            'id' => 188,
            'url' => 'https://example.com/hook-updated',
        ]),
        MockResponse::make('pong'),
        MockResponse::make('noop-ok'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $whoAmI = $sdk->whoAmI();
    $state = $sdk->account()->getState();
    $sdk->account()->setState(AccountState::Active);
    $tag = $sdk->account()->getTag('favorite');
    $apiKey = $sdk->account()->getApiKey('warehouse');
    $clientKey = $sdk->account()->clientKey('client-123', version: '4.7.1', edition: 'printnode');
    $download = $sdk->downloads()->latest(OperatingSystem::Windows);
    $sdk->downloads()->update(18, new DownloadClientPatchPayload(['enabled' => true]));
    $scales = $sdk->scales()->listConnected();
    $deviceScales = $sdk->scales()->byDeviceName(1, 'Scale A');
    $scale = $sdk->scales()->get(1, 'Scale A', 0);
    $sdk->scales()->test(['deviceName' => 'Scale A']);
    $webhooks = $sdk->webhooks()->all();
    $createdWebhook = $sdk->webhooks()->create(new WebhookPayload('https://example.com/hook'));
    $updatedWebhook = $sdk->webhooks()->update(188, new WebhookPayload('https://example.com/hook-updated'));
    $ping = $sdk->misc()->ping();
    $noop = $sdk->misc()->noop();

    expect($whoAmI->attributes['email'])->toBe('hello@example.com')
        ->and($state)->toBe('active')
        ->and($tag)->toBe('laser')
        ->and($apiKey)->toBe('api-key-value')
        ->and($clientKey['clientKey'])->toBe('client-123')
        ->and($download->attributes['id'])->toBe(18)
        ->and($scales[0]->attributes['deviceName'])->toBe('Scale A')
        ->and($deviceScales[0]->attributes['deviceName'])->toBe('Scale A')
        ->and($scale->attributes['deviceNum'])->toBe(0)
        ->and($webhooks[0]->attributes['id'])->toBe(188)
        ->and($createdWebhook->attributes['url'])->toBe('https://example.com/hook')
        ->and($updatedWebhook->attributes['url'])->toBe('https://example.com/hook-updated')
        ->and($ping)->toBe('pong')
        ->and($noop)->toBe('noop-ok');

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/account/state'
            && $pendingRequest->getMethod()->value === 'PUT'
            && (string) $pendingRequest->body() === '"active"';
    });
});
