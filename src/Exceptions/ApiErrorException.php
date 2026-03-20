<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Exceptions;

class ApiErrorException extends PrintNodeException
{
    /**
     * @param  array<mixed>|null  $payload
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $requestId = null,
        public readonly ?array $payload = null,
    ) {
        parent::__construct($message, $status);
    }
}
