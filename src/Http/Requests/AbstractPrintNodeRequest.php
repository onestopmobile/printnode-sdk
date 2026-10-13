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
    public const string IDEMPOTENCY_KEY_HEADER = 'X-Idempotency-Key';

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

    /**
     * The child account this request should run in. Null means: whatever the connector defaults to.
     * The connector turns this into a header while booting the request, see PrintNodeConnector::boot().
     */
    public function childAccount(): ?ChildAccountContext
    {
        return $this->childAccount;
    }

    protected function defaultHeaders(): array
    {
        return $this->extraHeaders;
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
