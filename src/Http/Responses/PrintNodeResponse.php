<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http\Responses;

use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Exceptions\AuthenticationException;
use OneStopMobile\PrintNodeSdk\Exceptions\AuthorizationException;
use OneStopMobile\PrintNodeSdk\Exceptions\ConflictException;
use OneStopMobile\PrintNodeSdk\Exceptions\RateLimitException;
use OneStopMobile\PrintNodeSdk\Exceptions\ResourceNotFoundException;
use OneStopMobile\PrintNodeSdk\Exceptions\ValidationException;
use Saloon\Http\Response;

class PrintNodeResponse extends Response
{
    public function requestId(): ?string
    {
        $value = $this->header('X-Request-Id');

        return is_string($value) ? $value : null;
    }

    public function retryAfter(): ?string
    {
        $value = $this->header('Retry-After');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function contentType(): ?string
    {
        $value = $this->header('Content-Type');

        return is_string($value) ? $value : null;
    }

    public function payload(): mixed
    {
        $decoded = json_decode($this->body(), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $this->body();
    }

    public function dtoOrThrow(): mixed
    {
        $this->throwIfFailed();

        return $this->dto() ?? $this->payload();
    }

    public function throwIfFailed(): static
    {
        if (! $this->failed()) {
            return $this;
        }

        $payload = $this->payload();
        $message = $this->resolveErrorMessage($payload);

        throw match ($this->status()) {
            400 => new ValidationException($message, 400, $this->requestId(), is_array($payload) ? $payload : null),
            401 => new AuthenticationException($message, 401, $this->requestId(), is_array($payload) ? $payload : null),
            403 => new AuthorizationException($message, 403, $this->requestId(), is_array($payload) ? $payload : null),
            404 => new ResourceNotFoundException($message, 404, $this->requestId(), is_array($payload) ? $payload : null),
            409 => new ConflictException($message, 409, $this->requestId(), is_array($payload) ? $payload : null),
            429 => new RateLimitException($message, 429, $this->requestId(), $this->retryAfter(), is_array($payload) ? $payload : null),
            default => new ApiErrorException($message, $this->status(), $this->requestId(), is_array($payload) ? $payload : null),
        };
    }

    protected function resolveErrorMessage(mixed $payload): string
    {
        if (is_array($payload)) {
            foreach (['message', 'error', 'detail'] as $key) {
                $value = $payload[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return sprintf('PrintNode API request failed with HTTP %d.', $this->status());
    }
}
