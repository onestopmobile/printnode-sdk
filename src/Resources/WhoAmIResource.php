<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\AccountData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class WhoAmIResource extends AbstractResource
{
    public function get(?ChildAccountContext $childAccount = null): AccountData
    {
        /** @var array<string, mixed> $account */
        $account = $this->send(new EndpointRequest(Method::GET, '/whoami', $childAccount));

        return AccountData::fromArray($account);
    }
}
