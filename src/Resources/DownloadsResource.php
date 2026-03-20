<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\DownloadClientData;
use OneStopMobile\PrintNodeSdk\Enums\OperatingSystem;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Payloads\DownloadClientPatchPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;
use Saloon\Enums\Method;

final readonly class DownloadsResource extends AbstractResource
{
    /**
     * @return list<DownloadClientData>
     */
    public function all(?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $downloads */
        $downloads = $this->send(new EndpointRequest(Method::GET, '/download/clients', $childAccount));

        return array_map(DownloadClientData::fromArray(...), $downloads);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $downloadIds
     * @return list<DownloadClientData>
     */
    public function get(int|array|CommaSeparatedIdSet|string $downloadIds, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $downloads */
        $downloads = $this->send(new EndpointRequest(
            Method::GET,
            '/download/clients/'.CommaSeparatedIdSet::from($downloadIds),
            $childAccount,
        ));

        return array_map(DownloadClientData::fromArray(...), $downloads);
    }

    public function latest(OperatingSystem|string $operatingSystem, ?ChildAccountContext $childAccount = null): DownloadClientData
    {
        $value = $operatingSystem instanceof OperatingSystem ? $operatingSystem->value : $operatingSystem;

        /** @var array<string, mixed> $download */
        $download = $this->send(new EndpointRequest(
            Method::GET,
            '/download/client/'.rawurlencode($value),
            $childAccount,
        ));

        return DownloadClientData::fromArray($download);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $downloadIds
     */
    public function update(
        int|array|CommaSeparatedIdSet|string $downloadIds,
        DownloadClientPatchPayload $payload,
        ?ChildAccountContext $childAccount = null,
    ): mixed {
        return $this->send(new JsonEndpointRequest(
            Method::PATCH,
            '/download/clients/'.CommaSeparatedIdSet::from($downloadIds),
            $payload->toArray(),
            $childAccount,
        ));
    }
}
