<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;
use OneStopMobile\PrintNodeSdk\Laravel\LaravelPrintNodeConfigFactory;
use OneStopMobile\PrintNodeSdk\Laravel\PrintNodeServiceProvider;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\Printing\EnvironmentPrintDispatchPolicy;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchContext;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

it('falls back to defaults when the laravel root config is invalid', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $repository = fakeConfigRepository([
        'printnode' => 'invalid',
    ]);
    $config = $factory->makePrintNodeConfig($repository);
    $managerConfig = $factory->makePrintManagerConfig($repository, new Container);

    expect($config->apiKey)->toBe('')
        ->and($config->baseUrl)->toBe('https://api.printnode.com')
        ->and($config->userAgent)->toBe('onestopmobile-printnode-sdk')
        ->and($config->defaultChildAccount)->toBeNull()
        ->and($managerConfig->defaultTitle)->toBe('Backoffice print job')
        ->and($managerConfig->defaultSource)->toBe('One Stop Mobile - Backoffice')
        ->and($managerConfig->dispatchPolicy)->toBeInstanceOf(EnvironmentPrintDispatchPolicy::class);
});

it('handles creator-ref child accounts and missing laravel services gracefully', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $repository = fakeConfigRepository([
        'printnode' => [
            'default_child_account' => [
                'by' => 'creator_ref',
                'value' => 'warehouse-ref',
            ],
            'printing' => [
                'logging' => 'invalid',
                'policy' => [
                    'enabled' => true,
                    'allowed_environments' => 1,
                    'action_outside_allowed_environments' => 'invalid',
                ],
            ],
        ],
    ]);
    $app = new Container;

    $config = $factory->makePrintNodeConfig($repository);
    $managerConfig = $factory->makePrintManagerConfig($repository, $app);
    $decision = $managerConfig->dispatchPolicy?->decide(new PrintDispatchContext(
        pendingPrint: new PrintNodeSdk(new PrintNodeConfig(
            apiKey: 'printnode-api-key',
            baseUrl: 'https://api.printnode.test',
        ))->printing()->printer(1),
        target: ResolvedPrintTarget::forPrinter(1),
        payload: new CreatePrintJobPayload(
            printerId: 1,
            title: 'Factory policy',
            contentType: PrintContentType::PdfBase64,
            content: 'JVBERi0xLjQ=',
        ),
    ));

    expect($config->defaultChildAccount?->headerName)->toBe('X-Child-Account-By-CreatorRef')
        ->and($config->defaultChildAccount?->value)->toBe('warehouse-ref')
        ->and($managerConfig->logger)->toBeNull()
        ->and($managerConfig->dispatchPolicy)->toBeInstanceOf(EnvironmentPrintDispatchPolicy::class)
        ->and($decision?->action)->toBe(PrintDispatchAction::Send);
});

it('parses comma-separated allowed environments from the published config shape', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $managerConfig = $factory->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'policy' => [
                    'allowed_environments' => 'production, staging',
                ],
            ],
        ],
    ]), fakeLaravelApp([], 'staging'));
    $decision = $managerConfig->dispatchPolicy?->decide(new PrintDispatchContext(
        pendingPrint: new PrintNodeSdk(new PrintNodeConfig(
            apiKey: 'printnode-api-key',
            baseUrl: 'https://api.printnode.test',
        ))->printing()->printer(1),
        target: ResolvedPrintTarget::forPrinter(1),
        payload: new CreatePrintJobPayload(
            printerId: 1,
            title: 'Factory policy',
            contentType: PrintContentType::PdfBase64,
            content: 'JVBERi0xLjQ=',
        ),
    ));

    expect($managerConfig->dispatchPolicy)->toBeInstanceOf(EnvironmentPrintDispatchPolicy::class)
        ->and($decision?->action)->toBe(PrintDispatchAction::Send);
});

it('returns a null logger when logging is configured without a log binding', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $managerConfig = $factory->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'printnode',
                ],
            ],
        ],
    ]), new Container);

    expect($managerConfig->logger)->toBeNull();
});

it('can disable both the print guard and logging explicitly', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $managerConfig = $factory->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'policy' => [
                    'enabled' => false,
                ],
                'logging' => [
                    'enabled' => false,
                ],
            ],
        ],
    ]), new Container);

    expect($managerConfig->dispatchPolicy)->toBeNull()
        ->and($managerConfig->logger)->toBeNull();
});

it('falls back to the default logger when the preferred channel is unavailable', function (): void {
    $factory = new LaravelPrintNodeConfigFactory;
    $logger = new class extends AbstractLogger
    {
        public array $records = [];

        /**
         * @param  array<string, mixed>  $context
         */
        public function log($level, $message, array $context = []): void
        {
            $this->records[] = [
                'level' => (string) $level,
                'message' => (string) $message,
                'context' => $context,
            ];
        }

        public function channel(string $name): LoggerInterface
        {
            throw new RuntimeException('Unknown log channel: '.$name);
        }
    };
    $app = new Container;
    $app->instance('log', $logger);

    $managerConfig = $factory->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'print-node',
                ],
            ],
        ],
    ]), $app);

    expect($managerConfig->logger)->toBe($logger);
});

it('throws when the laravel config binding is not a config repository', function (): void {
    $app = new Container;
    $app->instance('config', new class
    {
        /**
         * @var array<string, mixed>
         */
        private array $items = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }

        public function set(string $key, mixed $value = null): void
        {
            $this->items[$key] = $value;
        }
    });

    $provider = new PrintNodeServiceProvider($app);
    $provider->register();

    $app->make(PrintNodeConfig::class);
})->throws(RuntimeException::class, 'Laravel config repository is not available.');
