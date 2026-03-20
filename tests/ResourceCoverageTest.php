<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Payloads\CreateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\Payloads\UpdateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\Pagination;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('covers additional account endpoints and creator-ref child account headers', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $childAccount = ChildAccountContext::byCreatorRef('warehouse-ref');
    $mockClient = new MockClient([
        MockResponse::make([
            'Account' => [
                'id' => 101,
                'firstname' => '-',
                'lastname' => '-',
                'email' => 'created@example.com',
                'creatorRef' => 'warehouse-ref',
            ],
            'ApiKeys' => ['Warehouse Key'],
            'Tags' => [
                'warehouse' => 'A1',
            ],
        ]),
        MockResponse::make([
            'id' => 101,
            'email' => 'updated@example.com',
        ]),
        MockResponse::make(''),
        MockResponse::make([
            [
                'id' => 201,
                'email' => 'control-1@example.com',
            ],
            [
                'id' => 202,
                'email' => 'control-2@example.com',
            ],
        ]),
        MockResponse::make('tag-saved'),
        MockResponse::make('tag-deleted'),
        MockResponse::make('api-key-created'),
        MockResponse::make('api-key-deleted'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $created = $sdk->account()->create(
        new CreateChildAccountPayload(
            email: 'created@example.com',
            password: 'secret-password',
            creatorRef: 'warehouse-ref',
            apiKeys: ['Warehouse Key'],
            tags: [
                'warehouse' => 'A1',
            ],
        ),
    );
    $updated = $sdk->account()->update(
        new UpdateChildAccountPayload(
            email: 'updated@example.com',
        ),
        $childAccount,
    );
    $sdk->account()->delete($childAccount);
    $controllable = $sdk->account()->controllable($childAccount);
    $tagSaved = $sdk->account()->setTag('warehouse tag', 'laser', $childAccount);
    $tagDeleted = $sdk->account()->deleteTag('warehouse tag', $childAccount);
    $apiKeyCreated = $sdk->account()->createApiKey('Warehouse Key', $childAccount);
    $apiKeyDeleted = $sdk->account()->deleteApiKey('Warehouse Key', $childAccount);

    expect($childAccount->toHeaders())->toBe([
        'X-Child-Account-By-CreatorRef' => 'warehouse-ref',
    ])
        ->and($created->id)->toBe(101)
        ->and($created->apiKeys)->toBe(['Warehouse Key'])
        ->and($created->tags)->toBe(['warehouse' => 'A1'])
        ->and($updated->email)->toBe('updated@example.com')
        ->and($controllable)->toHaveCount(2)
        ->and($controllable[0]->id)->toBe(201)
        ->and($tagSaved)->toBe('tag-saved')
        ->and($tagDeleted)->toBe('tag-deleted')
        ->and($apiKeyCreated)->toBe('api-key-created')
        ->and($apiKeyDeleted)->toBe('api-key-deleted');

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/account'
            && json_decode((string) $pendingRequest->body(), true, 512, JSON_THROW_ON_ERROR) === [
                'Account' => [
                    'firstname' => '-',
                    'lastname' => '-',
                    'email' => 'created@example.com',
                    'password' => 'secret-password',
                    'creatorRef' => 'warehouse-ref',
                ],
                'ApiKeys' => ['Warehouse Key'],
                'Tags' => [
                    'warehouse' => 'A1',
                ],
            ];
    });

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/account/tag/warehouse%20tag'
            && (string) $pendingRequest->body() === 'laser';
    });

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/account/apikey/Warehouse%20Key');
});

it('covers additional collection and deletion resource endpoints', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make([
            [
                'id' => 575407,
                'state' => 'connected',
            ],
            [
                'id' => 609529,
                'state' => 'connected',
            ],
        ]),
        MockResponse::make([575407, 609529]),
        MockResponse::make([575407]),
        MockResponse::make([
            [
                'id' => 18,
                'os' => 'windows',
            ],
            [
                'id' => 19,
                'os' => 'mac',
            ],
        ]),
        MockResponse::make([
            [
                'id' => 18,
                'os' => 'windows',
            ],
        ]),
        MockResponse::make([
            [
                'id' => 42,
                'name' => 'Brother',
            ],
        ]),
        MockResponse::make([
            [
                'computer' => 1,
                'deviceName' => 'Scale A',
            ],
        ]),
        MockResponse::make('webhook-deleted'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $computers = $sdk->computers()->get([575407, 609529]);
    $deletedAllComputers = $sdk->computers()->delete();
    $deletedSelectedComputers = $sdk->computers()->delete([575407]);
    $downloads = $sdk->downloads()->all();
    $selectedDownloads = $sdk->downloads()->get([18, 19]);
    $printers = $sdk->printers()->all();
    $scales = $sdk->scales()->all(1);
    $deletedWebhook = $sdk->webhooks()->delete(188);

    expect($computers)->toHaveCount(2)
        ->and($computers[0]->id)->toBe(575407)
        ->and($deletedAllComputers)->toBe([575407, 609529])
        ->and($deletedSelectedComputers)->toBe([575407])
        ->and($downloads)->toHaveCount(2)
        ->and($selectedDownloads)->toHaveCount(1)
        ->and($selectedDownloads[0]->id)->toBe(18)
        ->and($printers[0]->id)->toBe(42)
        ->and($scales[0]->deviceName)->toBe('Scale A')
        ->and($deletedWebhook)->toBe('webhook-deleted');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/computers/575407,609529');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/computers');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/computers/575407');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/download/clients');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/download/clients/18,19');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printers');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/computer/1/scales');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/webhook/188');
});

it('covers additional print job resource methods', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));
    $mockClient = new MockClient([
        MockResponse::make([
            [
                'id' => 10,
                'state' => 'queued',
            ],
            [
                'id' => 11,
                'state' => 'done',
            ],
        ]),
        MockResponse::make([
            [
                'id' => 10,
                'state' => 'queued',
            ],
        ]),
        MockResponse::make([10, 11]),
        MockResponse::make([12, 13]),
        MockResponse::make([
            [
                [
                    'state' => 'queued',
                ],
            ],
        ]),
        MockResponse::make([12, 13]),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $allJobs = $sdk->printJobs()->all(new Pagination(limit: 1, after: 5));
    $selectedJobs = $sdk->printJobs()->get([10, 11]);
    $deletedAllJobs = $sdk->printJobs()->delete();
    $deletedSelectedJobs = $sdk->printJobs()->delete([12, 13]);
    $states = $sdk->printJobs()->states([10, 11], new Pagination(limit: 2));
    $deletedByPrinters = $sdk->printJobs()->deleteByPrinters([5, 6], [12, 13]);

    expect($allJobs)->toHaveCount(2)
        ->and($selectedJobs)->toHaveCount(1)
        ->and($deletedAllJobs)->toBe([10, 11])
        ->and($deletedSelectedJobs)->toBe([12, 13])
        ->and($states[0][0]->state)->toBe('queued')
        ->and($deletedByPrinters)->toBe([12, 13]);

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();
        $query = $pendingRequest->getRequest()->query()->all();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/printjobs'
            && $query === [
                'limit' => 1,
                'after' => 5,
            ];
    });

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printjobs/10,11');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printjobs');

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printjobs/12,13');

    $mockClient->assertSent(function ($request, $response): bool {
        $pendingRequest = $response->getPendingRequest();
        $query = $pendingRequest->getRequest()->query()->all();

        return $pendingRequest->getUrl() === 'https://api.printnode.test/printjobs/10,11/states'
            && $query === [
                'limit' => 2,
            ];
    });

    $mockClient->assertSent(fn ($request, $response): bool => $response->getPendingRequest()->getUrl() === 'https://api.printnode.test/printers/5,6/printjobs/12,13');
});
