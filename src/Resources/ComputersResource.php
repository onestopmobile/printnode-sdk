<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\ComputerData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;
use OneStopMobile\PrintNodeSdk\Values\Pagination;
use Saloon\Enums\Method;

final readonly class ComputersResource extends AbstractResource
{
    /**
     * @return list<ComputerData>
     */
    public function all(?Pagination $pagination = null, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $computers */
        $computers = $this->send(new EndpointRequest(
            Method::GET,
            '/computers',
            $childAccount,
            $pagination?->toQuery() ?? [],
        ));

        return array_map(ComputerData::fromArray(...), $computers);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $computerSet
     * @return list<ComputerData>
     */
    public function get(int|array|CommaSeparatedIdSet|string $computerSet, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $computers */
        $computers = $this->send(new EndpointRequest(
            Method::GET,
            '/computers/'.CommaSeparatedIdSet::from($computerSet),
            $childAccount,
        ));

        return array_map(ComputerData::fromArray(...), $computers);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $computerSet
     */
    public function delete(int|array|CommaSeparatedIdSet|string|null $computerSet = null, ?ChildAccountContext $childAccount = null): mixed
    {
        $endpoint = '/computers';

        if ($computerSet !== null) {
            $endpoint .= '/'.CommaSeparatedIdSet::from($computerSet);
        }

        return $this->send(new EndpointRequest(Method::DELETE, $endpoint, $childAccount));
    }
}
