<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http\Requests;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final class EndpointRequest extends AbstractPrintNodeRequest
{
    /**
     * @param  array<string, mixed>  $queryParameters
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        protected Method $method,
        private readonly string $endpoint,
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
}
