# Changelog

All notable changes to this project will be documented in this file.

## 0.4.0 - 2026-11-21

### Breaking changes

- `account()->update()`, `setState()`, `setTag()`, `deleteTag()`, `createApiKey()` and `deleteApiKey()` need the account they change. Without one they ran in the connector's default child account, or in the integrator account when there was no default. Pass `ChildAccountContext::parentAccount()` to change the integrator account on purpose.
- `account()->setState()` refuses `ChildAccountContext::parentAccount()`: suspending the integrator account would stop printing for every child account.
- Requires `saloonphp/saloon` ^4.4 (was ^4.0): waiting for `Retry-After` uses `Saloon\Config::sleep()`, which 4.4.0 added.
- A connection that fails or times out after the last attempt throws `ConnectionException` (a `PrintNodeException`) instead of Saloon's `FatalRequestException`. The Saloon exception is its `getPrevious()`.

### Fixed

- After a 429 the connector waits as long as `Retry-After` asks (one second when it does not say) before it tries again, instead of only the configured interval (0 ms by default). A `Retry-After` longer than 10 seconds ends the attempts with a `RateLimitException`, so a queued job can come back later instead of holding a worker.

### Docs

- `wasAlreadySent()` means PrintNode received the job earlier, not that it printed: give a reprint its own key.
- Send documents with personal data with `pdfBase64()`: with `pdfUrl()` PrintNode downloads the file from a public address.
- Titles and print options are logged as they are, so keep personal data out of a title.

## 0.3.0 - 2026-10-14

### Breaking changes

- `computers()->delete()` and `printJobs()->delete()` need ids. To delete everything, call `deleteAll()`.
- `printJobs()->deleteByPrinters()` needs print job ids. To clear whole printers, call `deleteAllByPrinters()`.
- `account()->delete()` needs the child account to delete, and refuses `ChildAccountContext::parentAccount()`.
- Id sets reject every invalid part instead of skipping it: `'12,abc'`, `'1.9'`, `'-5'` and a trailing comma now throw `InvalidIdentifierSetException`.
- `default_idempotency_prefix` no longer derives a key from the content. It now prefixes the keys you set with `->idempotencyKey()`. Prints without a key are always sent.
- `expireAfter` is an `int` (seconds) on `CreatePrintJobPayload` and `PendingPrint`.
- The retry interval from the Laravel config (`PRINTNODE_HTTP_RETRY_INTERVAL_SECONDS`) is now really in seconds. It was read as milliseconds before.
- The published config and the Laravel factory default the print title and source to `Print job` and `PrintNode SDK`.

### Fixed

- With `tries` above 1, only reads and print jobs with an idempotency key are retried, and only after a connection failure, a 429 or a 5xx. A print job without a key is never sent twice. After the last attempt you get the SDK's exceptions instead of Saloon's.
- A request that names a child account no longer also carries the connector's default child-account header.
- `account()->create()` always runs as the integrator account.
- A reused idempotency key (HTTP 409) is no longer reported as a failed print. `printJobs()->create()` throws `DuplicatePrintJobException` (a `ConflictException`), and `PrintManager` returns a `PrintResult` with `wasAlreadySent()`.
- Disabling logging with `PRINTNODE_PRINT_LOGGING_ENABLED=false` works with the published config.
- A log channel the app does not define falls back to the app's default logger instead of Laravel's emergency logger.

### Added

- `ChildAccountContext::parentAccount()` to run a call as the integrator account.
- A child account per print: `ResolvedPrintTarget::$childAccount` and `PendingPrint::childAccount()`.
- `PendingPrint::qty()` and `CreatePrintJobPayload::$qty`, the only way to print multiple copies of a raw job.

## 0.2.0 and earlier

- Add environment-aware print dispatch policy support.
- Add PSR-3 and Laravel-channel based print observability.
- Add Laravel config support for default child-account context.
- Add semantic `zpl()` and `zplBase64()` print helpers.
- Tighten identifier-set validation and response/error handling.
