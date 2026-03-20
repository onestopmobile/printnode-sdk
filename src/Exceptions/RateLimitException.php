<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Exceptions;

final class RateLimitException extends ApiErrorException
{
    /**
     * @param  array<mixed>|null  $payload
     */
    public function __construct(
        string $message,
        int $status,
        ?string $requestId = null,
        public readonly ?string $retryAfter = null,
        ?array $payload = null,
    ) {
        parent::__construct($message, $status, $requestId, $payload);
    }
}
