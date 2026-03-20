<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class MiscResource extends AbstractResource
{
    public function ping(): string
    {
        /** @var string $result */
        $result = $this->send(new EndpointRequest(Method::GET, '/ping'));

        return $result;
    }

    public function noop(?ChildAccountContext $childAccount = null): string
    {
        /** @var string $result */
        $result = $this->send(new EndpointRequest(Method::GET, '/noop', $childAccount));

        return $result;
    }
}
