<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http\Requests;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class JsonEndpointRequest extends AbstractPrintNodeRequest implements HasBody
{
    use HasJsonBody;

    /**
     * @param  array<string, mixed>  $bodyParameters
     * @param  array<string, mixed>  $queryParameters
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        protected Method $method,
        private readonly string $endpoint,
        private readonly array $bodyParameters = [],
        ?ChildAccountContext $childAccount = null,
        array $queryParameters = [],
        array $extraHeaders = [],
    ) {
        parent::__construct($childAccount, $queryParameters, $extraHeaders);
    }

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->bodyParameters;
    }
}
