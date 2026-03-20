<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http\Requests;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasStringBody;

final class StringBodyEndpointRequest extends AbstractPrintNodeRequest implements HasBody
{
    use HasStringBody;

    /**
     * @param  array<string, mixed>  $queryParameters
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        protected Method $method,
        private readonly string $endpoint,
        private readonly ?string $bodyContent = null,
        private readonly string $contentType = 'application/json',
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

    protected function defaultBody(): ?string
    {
        return $this->bodyContent;
    }

    #[Override]
    protected function defaultHeaders(): array
    {
        return [
            ...parent::defaultHeaders(),
            'Content-Type' => $this->contentType,
        ];
    }
}
