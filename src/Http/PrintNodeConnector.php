<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http;

use OneStopMobile\PrintNodeSdk\Http\Requests\AbstractPrintNodeRequest;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Override;
use Saloon\Config;
use Saloon\Contracts\Authenticator;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\HasTimeout;
use Saloon\Traits\RequestProperties\HasTries;

final class PrintNodeConnector extends Connector
{
    use HasTimeout;
    use HasTries;

    /**
     * The longest Retry-After the connector waits for itself. A longer one ends the attempts with a
     * RateLimitException that carries Retry-After, so a queued job can come back later instead of
     * holding a worker.
     */
    public const int MAX_RETRY_AFTER_SECONDS = 10;

    /**
     * How long to wait after a 429 that does not say how long.
     */
    public const int DEFAULT_RATE_LIMIT_WAIT_SECONDS = 1;

    public float $connectTimeout;

    public float $requestTimeout;

    public function __construct(
        private readonly PrintNodeConfig $printNodeConfig,
    ) {
        $this->connectTimeout = $printNodeConfig->connectTimeout;
        $this->requestTimeout = $printNodeConfig->requestTimeout;
        $this->tries = $printNodeConfig->tries;
        $this->retryInterval = $printNodeConfig->retryInterval;
        $this->useExponentialBackoff = $printNodeConfig->useExponentialBackoff;

        // When the last attempt fails, hand that response back instead of throwing Saloon's own
        // exception, so PrintNodeResponse::throwIfFailed() turns it into one of the SDK's exceptions.
        $this->throwOnMaxTries = false;
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->printNodeConfig->baseUrl, '/');
    }

    /**
     * A request runs in exactly one account. Saloon merges connector and request headers by name,
     * so a request for one child account (say by creator ref) would otherwise also carry the
     * connector's default (say by email), and PrintNode would have to pick one. When the request
     * names an account, that choice replaces the default; parentAccount() removes it entirely.
     */
    #[Override]
    public function boot(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();
        $childAccount = $request instanceof AbstractPrintNodeRequest ? $request->childAccount() : null;

        if (! $childAccount instanceof ChildAccountContext) {
            return;
        }

        $headers = $pendingRequest->headers();

        foreach (ChildAccountContext::HEADER_NAMES as $headerName) {
            $headers->remove($headerName);
        }

        $headers->merge($childAccount->toHeaders());
    }

    /**
     * Only try again when sending the same request twice cannot do anything twice, and only for
     * failures that tend to go away on their own. A print job without an idempotency key is never
     * repeated: if PrintNode accepted it just before the connection dropped, a retry would print
     * the same receipt or label a second time.
     *
     * After a 429 the connector waits as long as PrintNode asks (Retry-After) before the next attempt,
     * on top of the configured interval, and stops when that is longer than MAX_RETRY_AFTER_SECONDS.
     */
    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if (! $this->isSafeToRepeat($request) || ! $this->isTemporaryFailure($exception)) {
            return false;
        }

        if (! $exception instanceof RequestException || $exception->getResponse()->status() !== 429) {
            return true;
        }

        $seconds = $this->secondsToWait($exception->getResponse()->header('Retry-After'));

        if ($seconds > self::MAX_RETRY_AFTER_SECONDS) {
            return false;
        }

        Config::sleep($seconds * 1_000_000);

        return true;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => $this->printNodeConfig->userAgent,
            ...($this->printNodeConfig->defaultChildAccount?->toHeaders() ?? []),
        ];
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator($this->printNodeConfig->apiKey, '');
    }

    private function isSafeToRepeat(Request $request): bool
    {
        return $request->getMethod() === Method::GET
            || $request->headers()->get(AbstractPrintNodeRequest::IDEMPOTENCY_KEY_HEADER) !== null;
    }

    /**
     * Retry-After is either a number of seconds or an HTTP date.
     */
    private function secondsToWait(mixed $retryAfter): int
    {
        $value = is_string($retryAfter) ? trim($retryAfter) : '';

        if ($value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        $time = $value === '' ? false : strtotime($value);

        return $time === false ? self::DEFAULT_RATE_LIMIT_WAIT_SECONDS : max(0, $time - time());
    }

    private function isTemporaryFailure(FatalRequestException|RequestException $exception): bool
    {
        // No response at all: the connection failed or timed out.
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        return $status === 429 || $status >= 500;
    }
}
