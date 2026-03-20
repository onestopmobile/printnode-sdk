<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Http\PrintNodeConnector;
use OneStopMobile\PrintNodeSdk\Http\Requests\AbstractPrintNodeRequest;
use OneStopMobile\PrintNodeSdk\Http\Responses\PrintNodeResponse;
use UnexpectedValueException;

abstract readonly class AbstractResource
{
    public function __construct(
        protected PrintNodeConnector $connector,
    ) {}

    protected function send(AbstractPrintNodeRequest $request): mixed
    {
        /** @var PrintNodeResponse $response */
        $response = $this->connector->send($request);

        return $response->dtoOrThrow();
    }

    protected function stringResponse(mixed $payload, string $context): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (is_int($payload) || is_float($payload)) {
            return (string) $payload;
        }

        throw new UnexpectedValueException(sprintf('Expected %s to return a string response.', $context));
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapResponse(mixed $payload, string $context): array
    {
        if (is_array($payload) && ! array_is_list($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        throw new UnexpectedValueException(sprintf('Expected %s to return an object-like response payload.', $context));
    }

    /**
     * @return list<int|string>
     */
    protected function identifierListResponse(mixed $payload, string $context): array
    {
        if (! is_array($payload)) {
            throw new UnexpectedValueException(sprintf('Expected %s to return a list of identifiers.', $context));
        }

        $identifiers = [];

        foreach ($payload as $value) {
            if (is_int($value)) {
                $identifiers[] = $value;

                continue;
            }

            if (is_string($value) && $value !== '') {
                $identifiers[] = ctype_digit($value) ? (int) $value : $value;

                continue;
            }

            throw new UnexpectedValueException(sprintf('Expected %s to contain only scalar identifiers.', $context));
        }

        return $identifiers;
    }

    protected function printJobIdResponse(mixed $payload): int|string
    {
        if (is_int($payload)) {
            return $payload;
        }

        if (is_string($payload) && $payload !== '') {
            return ctype_digit($payload) ? (int) $payload : $payload;
        }

        if (is_array($payload) && isset($payload['id']) && (is_int($payload['id']) || is_string($payload['id']))) {
            return is_string($payload['id']) && ctype_digit($payload['id']) ? (int) $payload['id'] : $payload['id'];
        }

        throw new UnexpectedValueException('Expected the PrintNode API to return a recognizable print job identifier.');
    }
}
