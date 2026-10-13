# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

- Add environment-aware print dispatch policy support.
- Add PSR-3 and Laravel-channel based print observability.
- Add Laravel config support for default child-account context.
- Add semantic `zpl()` and `zplBase64()` print helpers.
- Tighten identifier-set validation and response/error handling.

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
