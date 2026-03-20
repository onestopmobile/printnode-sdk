# One Stop Mobile PrintNode SDK

[![Tests](https://github.com/onestopmobile/printnode-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/onestopmobile/printnode-sdk/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/onestopmobile/printnode-sdk/graph/badge.svg)](https://codecov.io/gh/onestopmobile/printnode-sdk)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/onestopmobile/printnode-sdk.svg?style=flat-square)](https://packagist.org/packages/onestopmobile/printnode-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/onestopmobile/printnode-sdk.svg?style=flat-square)](https://packagist.org/packages/onestopmobile/printnode-sdk)
[![License](https://img.shields.io/packagist/l/onestopmobile/printnode-sdk.svg?style=flat-square)](https://packagist.org/packages/onestopmobile/printnode-sdk)

A PHP 8.5+ SDK for PrintNode with typed API access and an optional Laravel print layer.

```bash
composer require onestopmobile/printnode-sdk
```

Use it when you want:

- typed access to the PrintNode API
- a framework-agnostic core for any PHP application
- a higher-level Laravel printing API for common PDF and ZPL flows
- target-based printer resolution through your own application logic
- optional non-production print guards
- optional PSR-3 and Laravel log-channel support for print activity

## Choose Your Entry Point

- Use the core SDK if you want typed access to PrintNode resources from any PHP app or framework.
- Use the Laravel layer if you want short application code like `DispatchPrint::printer(...)->pdfUrl(...)` or `DispatchPrint::to(...)->zpl(...)`.

## Installation

Minimum requirement: `PHP 8.5`.

If you are working on this repository itself instead of consuming the package, use:

```bash
composer install
```

Recommended `.env` setup:

```dotenv
PRINTNODE_API_KEY=
PRINTNODE_API_BASE_URL=https://api.printnode.com
PRINTNODE_HTTP_USER_AGENT=onestopmobile-printnode-sdk
PRINTNODE_HTTP_CONNECT_TIMEOUT_SECONDS=10
PRINTNODE_HTTP_REQUEST_TIMEOUT_SECONDS=30
PRINTNODE_HTTP_RETRY_ATTEMPTS=1
PRINTNODE_HTTP_RETRY_INTERVAL_SECONDS=0
PRINTNODE_HTTP_USE_EXPONENTIAL_BACKOFF=false
PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_FIELD=
PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_VALUE=
PRINTNODE_PRINT_DEFAULT_TITLE="Backoffice print job"
PRINTNODE_PRINT_DEFAULT_SOURCE="One Stop Mobile - Backoffice"
PRINTNODE_PRINT_IDEMPOTENCY_KEY_PREFIX=
PRINTNODE_PRINT_GUARD_ENABLED=true
PRINTNODE_PRINT_ALLOWED_ENVIRONMENTS=production
PRINTNODE_PRINT_ACTION_OUTSIDE_ALLOWED_ENVIRONMENTS=skip
PRINTNODE_PRINT_LOGGING_ENABLED=true
PRINTNODE_PRINT_LOG_CHANNEL=print-node
PRINTNODE_PRINT_LOG_SKIPPED_JOBS=true
PRINTNODE_PRINT_LOG_SUCCESSFUL_JOBS=false
PRINTNODE_PRINT_LOG_FAILED_JOBS=true
PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_HASH=false
PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_LENGTH=true
```

## Supported API Areas

- `whoami`
- `computers`
- `printers`
- `printjobs`
- `scales` HTTP endpoints
- `webhooks`
- `account`
- `downloads`
- `misc` endpoints like `ping` and `noop`

## SDK Endpoint Matrix

| API area | SDK entry point | Public methods |
| --- | --- | --- |
| `whoami` | `whoAmI()`, `whoAmIResource()` | `get()` |
| `computers` | `computers()` | `all()`, `get()`, `delete()` |
| `printers` | `printers()` | `all()` |
| `printjobs` | `printJobs()` | `all()`, `get()`, `create()`, `delete()`, `states()`, `byPrinters()`, `deleteByPrinters()` |
| `downloads` | `downloads()` | `all()`, `get()`, `latest()`, `update()` |
| `scales` | `scales()` | `listConnected()`, `all()`, `byDeviceName()`, `get()`, `test()` |
| `webhooks` | `webhooks()` | `all()`, `create()`, `update()`, `delete()` |
| `account` | `account()` | `create()`, `update()`, `delete()`, `controllable()`, `getState()`, `setState()`, `getTag()`, `setTag()`, `deleteTag()`, `getApiKey()`, `createApiKey()`, `deleteApiKey()`, `clientKey()` |
| `misc` | `misc()` | `ping()`, `noop()` |

Full low-level SDK reference: [docs/sdk-reference.md](docs/sdk-reference.md).

## Typed API Usage

```php
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;

$sdk = new PrintNodeSdk(new PrintNodeConfig(
    apiKey: getenv('PRINTNODE_API_KEY') ?: '',
));

$whoAmI = $sdk->whoAmI();
$email = $whoAmI->email;
$computers = $sdk->computers()->all();
$printers = $sdk->printers()->all();

$printJobId = $sdk->printJobs()->create(
    new CreatePrintJobPayload(
        printerId: 123,
        title: 'Shipping label',
        contentType: PrintContentType::PdfUri,
        content: 'https://example.com/label.pdf',
    ),
    idempotencyKey: 'label-123',
);
```

## Typed child-account creation

```php
use OneStopMobile\PrintNodeSdk\Payloads\CreateChildAccountPayload;

$childAccount = $sdk->account()->create(
    new CreateChildAccountPayload(
        email: 'child@example.com',
        password: 'secret-password',
        creatorRef: 'customer-123',
        apiKeys: ['production'],
        tags: [
            'customer_id' => '123',
        ],
    ),
);
```

## Child-account impersonation

```php
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

$childAccount = ChildAccountContext::byEmail('warehouse@example.com');

$tags = $sdk->account()->getTag('team', $childAccount);
```

## Laravel integration

The package ships with `OneStopMobile\PrintNodeSdk\Laravel\PrintNodeServiceProvider` and a publishable config file:

Supported Laravel integration target: `12.x` and `13.x`.

```bash
php artisan vendor:publish --tag=printnode-config
```

Laravel uses the same recommended env names shown above.

For array- and class-based options like `allowed_environments`, `resolver`, and `default_options`, use the published `config/printnode.php` file.

## High-level printing API

The package also ships with a Laravel-friendly print abstraction for short application code.

Direct printing to a known PrintNode printer id:

```php
use OneStopMobile\PrintNodeSdk\Laravel\Facades\DispatchPrint;

$result = DispatchPrint::printer(123)->pdfUrl(
    'https://example.com/packing-slip.pdf',
    title: 'Pakbon 1001',
);
```

This is the fastest path when your application already knows the external PrintNode printer id.

Printing raw content:

```php
$result = DispatchPrint::printer(123)
    ->source('warehouse')
    ->option('copies', 2)
    ->raw('^XA^FO50,50^FDHello^FS^XZ', title: 'Label 1001');
```

Or use the semantic ZPL helper:

```php
$result = DispatchPrint::printer(123)
    ->source('warehouse')
    ->zpl('^XA^FO50,50^FDHello^FS^XZ', title: 'Label 1001');
```

Using dependency injection instead of the facade:

```php
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;

public function __invoke(PrintManager $print): void
{
    $print->printer(123)->pdfUrl(
        'https://example.com/packing-slip.pdf',
        title: 'Pakbon 1001',
    );
}
```

If you prefer to avoid a long list of named arguments when customizing the print layer, configure it through `PrintManagerConfig`:

```php
use OneStopMobile\PrintNodeSdk\Printing\PrintManagerConfig;

$print = $sdk->printingWithConfig(new PrintManagerConfig(
    defaultSource: 'warehouse',
    logFailures: true,
    logSuccess: false,
));
```

## Target-based printing

If your application does not store raw PrintNode printer ids everywhere, you can resolve arbitrary app targets through a user-defined resolver.

```php
$result = DispatchPrint::to($order->printer_reference)
    ->pdfUrl('https://example.com/packing-slip.pdf', title: 'Pakbon 1001');
```

The package itself does not assume anything about databases, Eloquent models or your own printer storage. You provide that behavior by binding `OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget`.

`to(...)` is the Laravel-facing name for this flow.

See `docs/laravel-printing.md` for the full resolver example and integration guide.

## Laravel print safety and logging

The Laravel integration can optionally protect physical printers outside selected environments and can log print activity through an existing Laravel log channel.

The published config defaults are intentionally safe:

- In `local` and `develop`, print jobs are skipped before they reach PrintNode and a skipped-job log entry is written.
- In `production`, the same defaults allow jobs to be sent to PrintNode.
- The default preferred log channel is `print-node`. If that channel is not configured in Laravel, the package falls back to the app logger.

Published `printnode.php` config supports:

```php
'default_child_account' => [
    'by' => 'email',
    'value' => 'warehouse@example.com',
],

'printing' => [
    'policy' => [
        'enabled' => true,
        'allowed_environments' => ['production'],
        'action_outside_allowed_environments' => 'skip',
    ],
    'logging' => [
        'enabled' => true,
        'channel' => 'stack',
        'log_skipped' => true,
        'log_success' => false,
        'log_failures' => true,
        'include_content_hash' => false,
        'include_content_length' => true,
    ],
],
```

When logging is enabled, the package logs metadata such as printer id, title, content type, request id and payload length. Payload hashes are optional and disabled by default. Raw ZPL/PDF content is not logged by default.

## Error handling

Failed API responses are mapped to SDK exceptions:

- `AuthenticationException`
- `AuthorizationException`
- `ValidationException`
- `ResourceNotFoundException`
- `ConflictException`
- `RateLimitException`
- `ApiErrorException`
- `PrintDispatchBlockedException`
- `InvalidIdentifierSetException`
- `UnresolvablePrintTargetException`
- `IncompletePrintJobException`

`RateLimitException` also exposes the raw `Retry-After` header when the API provides one.

## Development scripts

```bash
composer test:unit
composer analyse
composer test:lint
composer check
```

## Live GET smoke test

You can run a non-destructive live smoke test through the SDK itself with an API key argument:

```bash
composer smoke:get -- your-printnode-api-key
composer smoke:get -- your-printnode-api-key --extended
```

Default GET suite:

- `ping`
- `whoami`
- `computers`
- `printers`
- `printjobs` with `limit=5`
- `printjob states` with `limit=5`
- `download clients`

Extended GET suite:

- `account state`
- `controllable accounts`
- `webhooks`
- `connected scales`
