# Laravel Printing Guide

## Goal

The package includes a high-level Laravel printing layer so your application can dispatch PrintNode jobs from controllers, actions and queue jobs without repeating PrintNode payload code everywhere.

This layer is intentionally split in two modes:

- `printer(...)` for applications that already know the PrintNode printer id.
- `to(...)` for applications that want to resolve an app-specific reference through custom logic.

## Package boundary

The package does not assume:

- a database table for printers
- Eloquent models
- tenant storage
- a specific config structure in your app

That means the package can be reused in many Laravel applications without forcing one printer-storage strategy.

## Direct printing with a known PrintNode printer id

Use this when your application already has the external PrintNode printer id.

```php
use OneStopMobile\PrintNodeSdk\Laravel\Facades\DispatchPrint;

$result = DispatchPrint::printer(123)->pdfUrl(
    'https://example.com/packing-slip.pdf',
    title: 'Pakbon 1001',
);
```

The same flow works through dependency injection:

```php
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;

final class PrintPackingSlipAction
{
    public function __construct(
        private readonly PrintManager $print,
    ) {}

    public function __invoke(string $url): void
    {
        $this->print->printer(123)->pdfUrl($url, title: 'Pakbon 1001');
    }
}
```

## Raw printing

Use `raw(...)` when you have direct printer language content like ZPL.

```php
$result = DispatchPrint::printer(123)
    ->source('warehouse')
    ->option('copies', 2)
    ->raw('^XA^FO50,50^FDHello^FS^XZ', title: 'Label 1001');
```

`raw(...)` base64-encodes the content for you and dispatches it as `raw_base64`.

If you already have base64 content, use `rawBase64(...)`.

If you mainly print ZPL labels, use `zpl(...)` or `zplBase64(...)` as semantic aliases over the same raw-printing behavior.

## Target-based printing

Use `to(...)` when your application wants to resolve a local concept into a PrintNode printer id.

Examples of app-specific targets:

- an internal `printer_reference`
- an enum like `shipping-labels`
- a tenant-specific printer object
- a full Eloquent model

```php
$result = DispatchPrint::to($order->printer_reference)
    ->pdfUrl('https://example.com/packing-slip.pdf', title: 'Pakbon 1001');
```

To make this work, bind `OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget`.

## Resolver contract

The package contract looks like this:

```php
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;

interface ResolvesPrintTarget
{
    public function resolve(mixed $target): ResolvedPrintTarget;
}
```

And the resolver result contains:

- `printNodePrinterId`
- optional `source`
- optional `options`

## Example resolver

This is application code, not package code.

```php
use App\Models\Printer;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;

final class DatabasePrintTargetResolver implements ResolvesPrintTarget
{
    public function resolve(mixed $target): ResolvedPrintTarget
    {
        $printer = Printer::query()
            ->where('reference', $target)
            ->firstOrFail();

        return new ResolvedPrintTarget(
            printNodePrinterId: $printer->printnode_printer_id,
            source: $printer->default_source,
            options: $printer->default_options ?? [],
        );
    }
}
```

Register it in your app:

```php
use App\Printing\DatabasePrintTargetResolver;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;

$this->app->bind(ResolvesPrintTarget::class, DatabasePrintTargetResolver::class);
```

Or set the resolver class in published `printnode.php` config:

```php
'printing' => [
    'resolver' => App\Printing\DatabasePrintTargetResolver::class,
],
```

## Defaults and options

The package config supports these high-level printing defaults:

```php
'printing' => [
    'default_title' => 'Print job',
    'default_source' => 'PrintNode SDK',
    'default_options' => [],
    'default_idempotency_prefix' => null,
    'resolver' => null,
    'policy' => [
        'enabled' => false,
        'allowed_environments' => ['production'],
        'action_outside_allowed_environments' => 'skip',
    ],
    'logging' => [
        'enabled' => false,
        'channel' => null,
        'log_skipped' => true,
        'log_success' => false,
        'log_failures' => true,
        'include_content_hash' => false,
        'include_content_length' => true,
    ],
],
```

Behavior:

- explicit builder values win
- resolved target values are merged in
- package defaults are used as fallback

Options merge order:

1. package `default_options`
2. resolver-provided options
3. builder-provided options

## Idempotency

You can set an explicit key:

```php
DispatchPrint::printer(123)
    ->idempotencyKey('packing-slip-1001')
    ->pdfUrl('https://example.com/packing-slip.pdf', title: 'Pakbon 1001');
```

Or configure `default_idempotency_prefix` so the package derives a deterministic key from the print payload.

Use that only when your app wants identical payloads to collapse into the same PrintNode request identity.

## Environment policy

When you want to avoid accidental physical prints in local or staging environments, enable the Laravel policy:

```php
'printing' => [
    'policy' => [
        'enabled' => true,
        'allowed_environments' => ['production'],
        'action_outside_allowed_environments' => 'skip',
    ],
],
```

Supported actions outside the allowed environments:

- `skip`: return a skipped `PrintResult` without calling the PrintNode API
- `fail`: throw `PrintDispatchBlockedException`
- `send`: bypass the guard

## Logging

The Laravel integration can reuse an existing PSR-3 compatible logger or named Laravel log channel:

```php
'printing' => [
    'logging' => [
        'enabled' => true,
        'channel' => 'stack',
        'log_skipped' => true,
        'log_success' => false,
        'log_failures' => true,
    ],
],
```

Logged metadata is intentionally safe by default:

- printer id
- title
- source
- content type
- payload length
- idempotency key
- request id on success
- exception class/message on failure

Optional metadata:

- payload hash

The package does not log raw ZPL/PDF content unless your application adds that itself.

## Child-account defaults

If your Laravel app usually works inside one child account context, you can publish that once:

```php
'default_child_account' => [
    'by' => 'email', // or: id, creator_ref
    'value' => 'warehouse@example.com',
],
```

That config maps onto `PrintNodeConfig::$defaultChildAccount` and applies to connector requests automatically.

## Common usage patterns

### Controller

```php
public function print(Order $order): void
{
    DispatchPrint::to($order->printer_reference)
        ->pdfUrl($order->packing_slip_url, title: "Pakbon {$order->number}");
}
```

### Action class

```php
final class PrintShippingLabelAction
{
    public function __construct(
        private readonly PrintManager $print,
    ) {}

    public function __invoke(string $printerReference, string $zpl): void
    {
        $this->print->to($printerReference)
            ->source('shipping')
            ->raw($zpl, title: 'Shipping label');
    }
}
```

### Queue job

```php
final class DispatchPackingSlip implements ShouldQueue
{
    public function __construct(
        public string $printerReference,
        public string $url,
        public string $orderNumber,
    ) {}

    public function handle(PrintManager $print): void
    {
        $print->to($this->printerReference)
            ->pdfUrl($this->url, title: "Pakbon {$this->orderNumber}");
    }
}
```

## App-side printer sync

Many Laravel apps keep a local table of PrintNode computers/printers for UI selection, naming, or tenant-specific routing. Keep that sync code in the application and let the SDK stay focused on transport and dispatch.

A typical split looks like this:

1. Sync printers/computers from the PrintNode API into local tables in app code.
2. Resolve app-specific printer references with `ResolvesPrintTarget`.
3. Dispatch through `PrintManager` or `DispatchPrint`.

That keeps app storage flexible while still removing the repeated PrintNode HTTP and payload boilerplate from controllers, actions, and jobs.

## Exceptions

Package-level printing abstraction errors:

- `UnresolvablePrintTargetException`
- `IncompletePrintJobException`

PrintNode API errors still use the SDK exception tree:

- `AuthenticationException`
- `AuthorizationException`
- `ValidationException`
- `ResourceNotFoundException`
- `ConflictException`
- `RateLimitException`
- `ApiErrorException`
