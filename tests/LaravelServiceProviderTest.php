<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Laravel\Facades\DispatchPrint;
use OneStopMobile\PrintNodeSdk\Laravel\PrintNodeServiceProvider;
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\ArrayLogger;
use Tests\Support\FakeResolver;

it('registers laravel bindings and publishable config', function (): void {
    $app = fakeLaravelApp([
        'printnode' => [
            'api_key' => 'printnode-api-key',
            'base_url' => 'https://api.printnode.test',
            'user_agent' => 'sdk-test-agent',
            'default_child_account' => [
                'by' => 'email',
                'value' => 'warehouse@example.com',
            ],
            'printing' => [
                'default_title' => 'Queued print',
                'default_source' => 'Laravel print layer',
                'resolver' => FakeResolver::class,
            ],
        ],
    ]);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();
    $provider->boot();

    $config = $app->make(PrintNodeConfig::class);
    $sdk = $app->make(PrintNodeSdk::class);
    $printManager = $app->make(PrintManager::class);
    $resolver = $app->make(ResolvesPrintTarget::class);
    $publishPaths = ServiceProvider::pathsToPublish(PrintNodeServiceProvider::class, 'printnode-config');

    expect($config)->toBeInstanceOf(PrintNodeConfig::class)
        ->and($config->apiKey)->toBe('printnode-api-key')
        ->and($config->defaultChildAccount)->toBeInstanceOf(ChildAccountContext::class)
        ->and($config->defaultChildAccount?->headerName)->toBe('X-Child-Account-By-Email')
        ->and($config->defaultChildAccount?->value)->toBe('warehouse@example.com')
        ->and($sdk)->toBeInstanceOf(PrintNodeSdk::class)
        ->and($printManager)->toBeInstanceOf(PrintManager::class)
        ->and($resolver)->toBeInstanceOf(FakeResolver::class)
        ->and(array_values($publishPaths))->toContain('/tmp/laravel-config/printnode.php')
        ->and(array_keys($publishPaths))->toHaveCount(1);
});

it('supports the laravel dispatch facade', function (): void {
    $app = fakeLaravelApp([
        'printnode' => [
            'api_key' => 'printnode-api-key',
            'base_url' => 'https://api.printnode.test',
            'printing' => [
                'default_source' => 'Facade source',
            ],
        ],
    ]);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();
    $provider->boot();

    Facade::setFacadeApplication($app);

    $sdk = $app->make(PrintNodeSdk::class);
    $mockClient = new MockClient([
        MockResponse::make('3003'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = DispatchPrint::printer(123)->pdfUrl('https://example.com/facade.pdf', title: 'Facade print');

    expect($result->printJobId)->toBe(3003)
        ->and($result->printNodePrinterId)->toBe(123)
        ->and($result->source)->toBe('Facade source');

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('supports resolving print targets through to on the laravel dispatch facade', function (): void {
    $app = fakeLaravelApp([
        'printnode' => [
            'api_key' => 'printnode-api-key',
            'base_url' => 'https://api.printnode.test',
            'printing' => [
                'resolver' => FakeResolver::class,
            ],
        ],
    ]);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();
    $provider->boot();

    Facade::setFacadeApplication($app);

    $sdk = $app->make(PrintNodeSdk::class);
    $mockClient = new MockClient([
        MockResponse::make('3005'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = DispatchPrint::to('shipping-labels')->pdfUrl('https://example.com/facade-target.pdf', title: 'Facade target');

    expect($result->printJobId)->toBe(3005)
        ->and($result->printNodePrinterId)->toBe(999)
        ->and($result->source)->toBe('resolver:shipping-labels');

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('supports environment-aware policy and laravel log channels', function (): void {
    $logger = new ArrayLogger;
    $app = fakeLaravelApp([
        'printnode' => [
            'api_key' => 'printnode-api-key',
            'base_url' => 'https://api.printnode.test',
            'printing' => [
                'policy' => [
                    'enabled' => true,
                    'allowed_environments' => ['production'],
                    'action_outside_allowed_environments' => 'skip',
                ],
                'logging' => [
                    'enabled' => true,
                    'channel' => 'printnode',
                    'log_skipped' => true,
                ],
            ],
        ],
    ], environment: 'local', logger: $logger);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();
    $provider->boot();

    $sdk = $app->make(PrintNodeSdk::class);
    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $app->make(PrintManager::class)
        ->printer(123)
        ->zpl('^XA^FO50,50^FDlocal^FS^XZ', title: 'Local label');

    expect($result->wasSkipped())->toBeTrue()
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['context'])->toMatchArray([
            'printerId' => 123,
            'title' => 'Local label',
        ]);

    $mockClient->assertNothingSent();
});

it('parses string booleans and numeric child-account ids from laravel config', function (): void {
    $logger = new ArrayLogger;
    $app = fakeLaravelApp([
        'printnode' => [
            'api_key' => 'printnode-api-key',
            'default_child_account' => [
                'by' => 'id',
                'value' => 12,
            ],
            'printing' => [
                'policy' => [
                    'enabled' => 'true',
                    'allowed_environments' => ['production'],
                    'action_outside_allowed_environments' => 'skip',
                ],
                'logging' => [
                    'enabled' => 'true',
                    'channel' => 'printnode',
                    'log_skipped' => 'true',
                ],
            ],
        ],
    ], environment: 'local', logger: $logger);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();

    $config = $app->make(PrintNodeConfig::class);
    $sdk = $app->make(PrintNodeSdk::class);
    $printManager = $app->make(PrintManager::class);
    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);

    $sdk->connector()->withMockClient($mockClient);
    $result = $printManager->printer(124)->zpl('^XA^XZ', title: 'String config policy');

    expect($config->defaultChildAccount)->toBeInstanceOf(ChildAccountContext::class)
        ->and($config->defaultChildAccount?->headerName)->toBe('X-Child-Account-By-Id')
        ->and($config->defaultChildAccount?->value)->toBe('12')
        ->and($printManager)->toBeInstanceOf(PrintManager::class)
        ->and($result->wasSkipped())->toBeTrue()
        ->and($logger->records)->toHaveCount(1);

    $mockClient->assertNothingSent();
});

it('skips and logs print jobs by default outside production', function (): void {
    $logger = new ArrayLogger;
    $app = fakeLaravelApp(environment: 'local', logger: $logger);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();

    $sdk = $app->make(PrintNodeSdk::class);
    $mockClient = new MockClient([
        MockResponse::make('should-not-be-used'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $app->make(PrintManager::class)
        ->printer(125)
        ->zpl('^XA^XZ', title: 'Local default print');

    expect($result->wasSkipped())->toBeTrue()
        ->and($result->source)->toBe('One Stop Mobile - Backoffice')
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('info')
        ->and($logger->records[0]['context'])->toMatchArray([
            'printerId' => 125,
            'title' => 'Local default print',
        ]);

    $mockClient->assertNothingSent();
});

it('sends print jobs by default in production', function (): void {
    $logger = new ArrayLogger;
    $app = fakeLaravelApp(environment: 'production', logger: $logger);

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();

    $sdk = $app->make(PrintNodeSdk::class);
    $mockClient = new MockClient([
        MockResponse::make('3004'),
    ]);

    $sdk->connector()->withMockClient($mockClient);

    $result = $app->make(PrintManager::class)
        ->printer(126)
        ->pdfBase64('JVBERi0xLjQ=', title: 'Production default print');

    expect($result->wasSent())->toBeTrue()
        ->and($result->printJobId)->toBe(3004)
        ->and($result->source)->toBe('One Stop Mobile - Backoffice')
        ->and($logger->records)->toHaveCount(0);
});
