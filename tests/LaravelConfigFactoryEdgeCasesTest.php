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
use Psr\Log\NullLogger;

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
        ->and($managerConfig->defaultTitle)->toBe('Print job')
        ->and($managerConfig->defaultSource)->toBe('PrintNode SDK')
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

/**
 * Like Laravel's LogManager: an unknown channel is not an error, you silently get an emergency
 * logger that writes to laravel.log instead.
 */
function logManagerWithEmergencyFallback(): AbstractLogger
{
    return new class extends AbstractLogger
    {
        /**
         * @var list<string>
         */
        public array $requestedChannels = [];

        public function channel(string $name): LoggerInterface
        {
            $this->requestedChannels[] = $name;

            return new NullLogger;
        }

        /**
         * @param  array<string, mixed>  $context
         */
        public function log($level, string|Stringable $message, array $context = []): void {}
    };
}

it('uses the app default logger when the channel is not defined in the app', function (): void {
    $logManager = logManagerWithEmergencyFallback();
    $app = new Container;
    $app->instance('log', $logManager);

    $managerConfig = (new LaravelPrintNodeConfigFactory)->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'print-node',
                ],
            ],
        ],
    ]), $app);

    expect($managerConfig->logger)->toBe($logManager)
        ->and($logManager->requestedChannels)->toBe([]);
});

it('uses the configured channel when the app defines it', function (): void {
    $logManager = logManagerWithEmergencyFallback();
    $app = new Container;
    $app->instance('log', $logManager);

    $managerConfig = (new LaravelPrintNodeConfigFactory)->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'print-node',
                ],
            ],
        ],
        'logging.channels.print-node' => [
            'driver' => 'daily',
        ],
    ]), $app);

    expect($managerConfig->logger)->toBeInstanceOf(NullLogger::class)
        ->and($logManager->requestedChannels)->toBe(['print-node']);
});

it('logs nothing when logging is disabled, even with a channel set', function (): void {
    $app = new Container;
    $app->instance('log', logManagerWithEmergencyFallback());

    $managerConfig = (new LaravelPrintNodeConfigFactory)->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => false,
                    'channel' => 'print-node',
                ],
            ],
        ],
    ]), $app);

    expect($managerConfig->logger)->toBeNull();
});

it('logs nothing when the log binding is not a logger', function (): void {
    $app = new Container;
    $app->instance('log', new stdClass);

    $managerConfig = (new LaravelPrintNodeConfigFactory)->makePrintManagerConfig(fakeConfigRepository([
        'printnode' => [
            'printing' => [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'print-node',
                ],
            ],
        ],
        'logging.channels.print-node' => [
            'driver' => 'daily',
        ],
    ]), $app);

    expect($managerConfig->logger)->toBeNull();
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
