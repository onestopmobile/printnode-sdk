<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\ScaleData;
use OneStopMobile\PrintNodeSdk\Data\ScaleTestResultData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class ScalesResource extends AbstractResource
{
    /**
     * @return list<ScaleData>
     */
    public function listConnected(?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $scales */
        $scales = $this->send(new EndpointRequest(Method::GET, '/scales', $childAccount));

        return array_map(ScaleData::fromArray(...), $scales);
    }

    /**
     * @return list<ScaleData>
     */
    public function all(int $computerId, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $scales */
        $scales = $this->send(new EndpointRequest(Method::GET, sprintf('/computer/%d/scales', $computerId), $childAccount));

        return array_map(ScaleData::fromArray(...), $scales);
    }

    /**
     * @return list<ScaleData>
     */
    public function byDeviceName(int $computerId, string $deviceName, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $scales */
        $scales = $this->send(new EndpointRequest(
            Method::GET,
            sprintf('/computer/%d/scales/%s', $computerId, rawurlencode($deviceName)),
            $childAccount,
        ));

        return array_map(ScaleData::fromArray(...), $scales);
    }

    public function get(int $computerId, string $deviceName, int $deviceNumber, ?ChildAccountContext $childAccount = null): ScaleData
    {
        /** @var array<string, mixed> $scale */
        $scale = $this->send(new EndpointRequest(
            Method::GET,
            sprintf('/computer/%d/scale/%s/%d', $computerId, rawurlencode($deviceName), $deviceNumber),
            $childAccount,
        ));

        return ScaleData::fromArray($scale);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function test(array $payload, ?ChildAccountContext $childAccount = null): ScaleTestResultData
    {
        return ScaleTestResultData::fromArray($this->mapResponse(
            $this->send(new JsonEndpointRequest(Method::PUT, '/scale', $payload, $childAccount)),
            'PUT /scale',
        ));
    }
}
