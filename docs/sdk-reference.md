# SDK Endpoint Reference

This document covers the low-level core SDK resources that map closely to the PrintNode HTTP API.

For the higher-level Laravel printing layer, see [laravel-printing.md](laravel-printing.md).

## Quick Start

```php
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;

$sdk = new PrintNodeSdk(new PrintNodeConfig(
    apiKey: getenv('PRINTNODE_API_KEY') ?: '',
));

$account = $sdk->whoAmI();
$computers = $sdk->computers()->all();
```

## Common Concepts

### Child-account context

Many resource methods accept an optional `ChildAccountContext` so the request runs inside a specific child account.

Available factories:

- `ChildAccountContext::byId(int $id)`
- `ChildAccountContext::byEmail(string $email)`
- `ChildAccountContext::byCreatorRef(string $creatorRef)`

Example:

```php
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

$child = ChildAccountContext::byCreatorRef('customer-123');

$state = $sdk->account()->getState($child);
```

### Identifier sets

Methods that accept a set of ids support all of these forms:

- a single `int`
- an `array<int>`
- a comma-separated `string` like `'12,13,14'`
- `CommaSeparatedIdSet::from(...)`

Empty sets throw `InvalidIdentifierSetException`.

### Pagination

List endpoints that support pagination accept a `Pagination` value object.

```php
use OneStopMobile\PrintNodeSdk\Enums\SortDirection;
use OneStopMobile\PrintNodeSdk\Values\Pagination;

$jobs = $sdk->printJobs()->all(new Pagination(
    limit: 50,
    after: 1000,
    direction: SortDirection::Asc,
));
```

### Typed payload classes

The SDK currently exposes typed payload classes for the write-heavy endpoints:

- `CreatePrintJobPayload`
- `CreateChildAccountPayload`
- `UpdateChildAccountPayload`
- `WebhookPayload`
- `DownloadClientPatchPayload`

## Entry Points

The core SDK exposes these resource entry points:

- `PrintNodeSdk::whoAmI()`
- `PrintNodeSdk::whoAmIResource()`
- `PrintNodeSdk::computers()`
- `PrintNodeSdk::printers()`
- `PrintNodeSdk::printJobs()`
- `PrintNodeSdk::downloads()`
- `PrintNodeSdk::scales()`
- `PrintNodeSdk::webhooks()`
- `PrintNodeSdk::account()`
- `PrintNodeSdk::misc()`

## `whoami`

Convenience method:

- `PrintNodeSdk::whoAmI(): AccountData`

Resource method:

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `get(?ChildAccountContext $childAccount = null)` | `GET /whoami` | `AccountData` | Use the resource form when you need explicit child-account context. |

## `computers`

Entry point: `PrintNodeSdk::computers(): ComputersResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `all(?Pagination $pagination = null, ?ChildAccountContext $childAccount = null)` | `GET /computers` | `list<ComputerData>` | Supports `limit`, `after`, and `dir` through `Pagination`. |
| `get(int|array|CommaSeparatedIdSet|string $computerSet, ?ChildAccountContext $childAccount = null)` | `GET /computers/{computerSet}` | `list<ComputerData>` | Fetch one or many computers by id. |
| `delete(int|array|CommaSeparatedIdSet|string|null $computerSet = null, ?ChildAccountContext $childAccount = null)` | `DELETE /computers` or `DELETE /computers/{computerSet}` | `list<int|string>` | Returns the identifiers confirmed by the API. |

## `printers`

Entry point: `PrintNodeSdk::printers(): PrintersResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `all(int|array|CommaSeparatedIdSet|string|null $printerSet = null, int|array|CommaSeparatedIdSet|string|null $computerSet = null, ?ChildAccountContext $childAccount = null)` | `GET /printers`, `GET /printers/{printerSet}`, `GET /computers/{computerSet}/printers`, or `GET /computers/{computerSet}/printers/{printerSet}` | `list<PrinterData>` | Use `computerSet` to scope printers by computer and `printerSet` to further filter the result. |

## `printjobs`

Entry point: `PrintNodeSdk::printJobs(): PrintJobsResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `all(?Pagination $pagination = null, ?ChildAccountContext $childAccount = null)` | `GET /printjobs` | `list<PrintJobData>` | Supports `Pagination`. |
| `get(int|array|CommaSeparatedIdSet|string $printJobSet, ?ChildAccountContext $childAccount = null)` | `GET /printjobs/{printJobSet}` | `list<PrintJobData>` | Fetch one or many jobs by id. |
| `create(CreatePrintJobPayload $payload, ?string $idempotencyKey = null, ?ChildAccountContext $childAccount = null)` | `POST /printjobs` | `int|string` | Optional idempotency key is sent as `X-Idempotency-Key`. |
| `delete(int|array|CommaSeparatedIdSet|string|null $printJobSet = null, ?ChildAccountContext $childAccount = null)` | `DELETE /printjobs` or `DELETE /printjobs/{printJobSet}` | `list<int|string>` | Returns the identifiers confirmed by the API. |
| `states(int|array|CommaSeparatedIdSet|string|null $printJobSet = null, ?Pagination $pagination = null, ?ChildAccountContext $childAccount = null)` | `GET /printjobs/states` or `GET /printjobs/{printJobSet}/states` | `list<list<PrintJobStateData>>` | Returns one state-history list per print job. |
| `byPrinters(int|array|CommaSeparatedIdSet|string $printerSet, int|array|CommaSeparatedIdSet|string|null $printJobSet = null, ?ChildAccountContext $childAccount = null)` | `GET /printers/{printerSet}/printjobs` or `GET /printers/{printerSet}/printjobs/{printJobSet}` | `list<PrintJobData>` | Filters jobs by printer first, then optionally by print job id. |
| `deleteByPrinters(int|array|CommaSeparatedIdSet|string $printerSet, int|array|CommaSeparatedIdSet|string|null $printJobSet = null, ?ChildAccountContext $childAccount = null)` | `DELETE /printers/{printerSet}/printjobs` or `DELETE /printers/{printerSet}/printjobs/{printJobSet}` | `list<int|string>` | Deletes jobs scoped by printer. |

Create example:

```php
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;

$jobId = $sdk->printJobs()->create(
    new CreatePrintJobPayload(
        printerId: 123,
        title: 'Shipping label',
        contentType: PrintContentType::PdfUri,
        content: 'https://example.com/label.pdf',
    ),
    idempotencyKey: 'label-123',
);
```

## `downloads`

Entry point: `PrintNodeSdk::downloads(): DownloadsResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `all(?ChildAccountContext $childAccount = null)` | `GET /download/clients` | `list<DownloadClientData>` | Returns all known client downloads. |
| `get(int|array|CommaSeparatedIdSet|string $downloadIds, ?ChildAccountContext $childAccount = null)` | `GET /download/clients/{downloadIds}` | `list<DownloadClientData>` | Fetch one or many download records by id. |
| `latest(OperatingSystem|string $operatingSystem, ?ChildAccountContext $childAccount = null)` | `GET /download/client/{operatingSystem}` | `DownloadClientData` | Use `OperatingSystem` enum values when possible. |
| `update(int|array|CommaSeparatedIdSet|string $downloadIds, DownloadClientPatchPayload $payload, ?ChildAccountContext $childAccount = null)` | `PATCH /download/clients/{downloadIds}` | `DownloadClientUpdateData` | Returns a typed mutation result object. Use `raw()` if you need the untouched payload. |

Patch example:

```php
use OneStopMobile\PrintNodeSdk\Payloads\DownloadClientPatchPayload;

$sdk->downloads()->update(
    18,
    new DownloadClientPatchPayload(enabled: true),
);
```

## `scales`

Entry point: `PrintNodeSdk::scales(): ScalesResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `listConnected(?ChildAccountContext $childAccount = null)` | `GET /scales` | `list<ScaleData>` | Returns the current connected scale readings. |
| `all(int $computerId, ?ChildAccountContext $childAccount = null)` | `GET /computer/{computerId}/scales` | `list<ScaleData>` | Lists scale readings for one computer. |
| `byDeviceName(int $computerId, string $deviceName, ?ChildAccountContext $childAccount = null)` | `GET /computer/{computerId}/scales/{deviceName}` | `list<ScaleData>` | Filters scale readings by device name. |
| `get(int $computerId, string $deviceName, int $deviceNumber, ?ChildAccountContext $childAccount = null)` | `GET /computer/{computerId}/scale/{deviceName}/{deviceNumber}` | `ScaleData` | Fetches one scale reading. |
| `test(array $payload, ?ChildAccountContext $childAccount = null)` | `PUT /scale` | `ScaleTestResultData` | Returns a typed mutation result object. Use `raw()` if you need the untouched payload. |

## `webhooks`

Entry point: `PrintNodeSdk::webhooks(): WebhooksResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `all(?ChildAccountContext $childAccount = null)` | `GET /webhooks` | `list<WebhookData>` | Lists configured webhooks. |
| `create(WebhookPayload $payload, ?ChildAccountContext $childAccount = null)` | `POST /webhook` | `WebhookData` | Creates one webhook from a typed payload. |
| `update(int $id, WebhookPayload $payload, ?ChildAccountContext $childAccount = null)` | `PATCH /webhook/{id}` | `WebhookData` | Updates an existing webhook. |
| `delete(int $id, ?ChildAccountContext $childAccount = null)` | `DELETE /webhook/{id}` | `string` | Returns the textual API response body. |

## `account`

Entry point: `PrintNodeSdk::account(): AccountResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `create(CreateChildAccountPayload $payload)` | `POST /account` | `CreatedChildAccountData` | Creates a child account under the authenticated account. This method does not accept `ChildAccountContext`. |
| `update(UpdateChildAccountPayload $payload, ?ChildAccountContext $childAccount = null)` | `PATCH /account` | `AccountData` | Use `ChildAccountContext` when updating a specific child account. |
| `delete(?ChildAccountContext $childAccount = null)` | `DELETE /account` | `void` | Deletes the current account context. |
| `controllable(?ChildAccountContext $childAccount = null)` | `GET /account/controllable` | `list<AccountData>` | Lists child accounts the current account can control. |
| `getState(?ChildAccountContext $childAccount = null)` | `GET /account/state` | `string` | Reads the current account state. |
| `setState(AccountState|string $state, ?ChildAccountContext $childAccount = null)` | `PUT /account/state` | `void` | Accepts either the enum or a raw API state string. |
| `getTag(string $name, ?ChildAccountContext $childAccount = null)` | `GET /account/tag/{name}` | `string` | Reads one account tag value. |
| `setTag(string $name, string $value, ?ChildAccountContext $childAccount = null)` | `POST /account/tag/{name}` | `string` | Writes one account tag value. |
| `deleteTag(string $name, ?ChildAccountContext $childAccount = null)` | `DELETE /account/tag/{name}` | `string` | Deletes one tag by name. |
| `getApiKey(string $description, ?ChildAccountContext $childAccount = null)` | `GET /account/apikey/{description}` | `string` | Reads an API key value by description. |
| `createApiKey(string $description, ?ChildAccountContext $childAccount = null)` | `POST /account/apikey/{description}` | `string` | Creates an API key with the given description. |
| `deleteApiKey(string $description, ?ChildAccountContext $childAccount = null)` | `DELETE /account/apikey/{description}` | `string` | Deletes an API key by description. |
| `clientKey(string $key, ?string $version = null, ?string $edition = null, ?ChildAccountContext $childAccount = null)` | `GET /client/key/{key}` | `string` | Sends `version` and `edition` as query parameters. |

Child-account creation example:

```php
use OneStopMobile\PrintNodeSdk\Payloads\CreateChildAccountPayload;

$child = $sdk->account()->create(
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

Child-account impersonation example:

```php
use OneStopMobile\PrintNodeSdk\Payloads\UpdateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

$child = ChildAccountContext::byCreatorRef('customer-123');

$sdk->account()->update(
    new UpdateChildAccountPayload(email: 'new@example.com'),
    $child,
);
```

## `misc`

Entry point: `PrintNodeSdk::misc(): MiscResource`

| SDK method | PrintNode endpoint | Returns | Notes |
| --- | --- | --- | --- |
| `ping()` | `GET /ping` | `string` | Basic connectivity check. |
| `noop(?ChildAccountContext $childAccount = null)` | `GET /noop` | `string` | A non-mutating no-op request. |

## Related Docs

- [README](../README.md)
- [Laravel printing guide](laravel-printing.md)
