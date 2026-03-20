<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http\Requests;

use OneStopMobile\PrintNodeSdk\Http\Responses\PrintNodeResponse;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Override;
use Saloon\Http\Request;
use Saloon\Http\Response;

abstract class AbstractPrintNodeRequest extends Request
{
    #[Override]
    protected ?string $response = PrintNodeResponse::class;

    /**
     * @param  array<string, mixed>  $queryParameters
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        protected readonly ?ChildAccountContext $childAccount = null,
        protected readonly array $queryParameters = [],
        protected readonly array $extraHeaders = [],
    ) {}

    protected function defaultHeaders(): array
    {
        return array_filter([
            ...($this->childAccount?->toHeaders() ?? []),
            ...$this->extraHeaders,
        ], static fn (?string $value): bool => $value !== null);
    }

    protected function defaultQuery(): array
    {
        return array_filter(
            $this->queryParameters,
            static fn (mixed $value): bool => $value !== null,
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        if ($response instanceof PrintNodeResponse) {
            return $response->payload();
        }

        return $response->json();
    }
}
