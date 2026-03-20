<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\PrintJobData;
use OneStopMobile\PrintNodeSdk\Data\PrintJobStateData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;
use OneStopMobile\PrintNodeSdk\Values\Pagination;
use Saloon\Enums\Method;

final readonly class PrintJobsResource extends AbstractResource
{
    /**
     * @return list<PrintJobData>
     */
    public function all(?Pagination $pagination = null, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $jobs */
        $jobs = $this->send(new EndpointRequest(
            Method::GET,
            '/printjobs',
            $childAccount,
            $pagination?->toQuery() ?? [],
        ));

        return array_map(PrintJobData::fromArray(...), $jobs);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $printJobSet
     * @return list<PrintJobData>
     */
    public function get(int|array|CommaSeparatedIdSet|string $printJobSet, ?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $jobs */
        $jobs = $this->send(new EndpointRequest(
            Method::GET,
            '/printjobs/'.CommaSeparatedIdSet::from($printJobSet),
            $childAccount,
        ));

        return array_map(PrintJobData::fromArray(...), $jobs);
    }

    public function create(
        CreatePrintJobPayload $payload,
        ?string $idempotencyKey = null,
        ?ChildAccountContext $childAccount = null,
    ): mixed {
        return $this->send(new JsonEndpointRequest(
            Method::POST,
            '/printjobs',
            $payload->toArray(),
            $childAccount,
            extraHeaders: array_filter([
                'X-Idempotency-Key' => is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
            ], static fn (?string $value): bool => $value !== null),
        ));
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $printJobSet
     */
    public function delete(int|array|CommaSeparatedIdSet|string|null $printJobSet = null, ?ChildAccountContext $childAccount = null): mixed
    {
        $endpoint = '/printjobs';

        if ($printJobSet !== null) {
            $endpoint .= '/'.CommaSeparatedIdSet::from($printJobSet);
        }

        return $this->send(new EndpointRequest(Method::DELETE, $endpoint, $childAccount));
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $printJobSet
     * @return list<list<PrintJobStateData>>
     */
    public function states(
        int|array|CommaSeparatedIdSet|string|null $printJobSet = null,
        ?Pagination $pagination = null,
        ?ChildAccountContext $childAccount = null,
    ): array {
        $endpoint = $printJobSet === null
            ? '/printjobs/states'
            : '/printjobs/'.CommaSeparatedIdSet::from($printJobSet).'/states';

        /** @var list<list<array<string, mixed>>> $states */
        $states = $this->send(new EndpointRequest(
            Method::GET,
            $endpoint,
            $childAccount,
            $pagination?->toQuery() ?? [],
        ));

        return array_map(
            static fn (array $jobStates): array => array_map(
                PrintJobStateData::fromArray(...),
                $jobStates,
            ),
            $states,
        );
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $printerSet
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $printJobSet
     * @return list<PrintJobData>
     */
    public function byPrinters(
        int|array|CommaSeparatedIdSet|string $printerSet,
        int|array|CommaSeparatedIdSet|string|null $printJobSet = null,
        ?ChildAccountContext $childAccount = null,
    ): array {
        $endpoint = '/printers/'.CommaSeparatedIdSet::from($printerSet).'/printjobs';

        if ($printJobSet !== null) {
            $endpoint .= '/'.CommaSeparatedIdSet::from($printJobSet);
        }

        /** @var list<array<string, mixed>> $jobs */
        $jobs = $this->send(new EndpointRequest(Method::GET, $endpoint, $childAccount));

        return array_map(PrintJobData::fromArray(...), $jobs);
    }

    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string  $printerSet
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $printJobSet
     */
    public function deleteByPrinters(
        int|array|CommaSeparatedIdSet|string $printerSet,
        int|array|CommaSeparatedIdSet|string|null $printJobSet = null,
        ?ChildAccountContext $childAccount = null,
    ): mixed {
        $endpoint = '/printers/'.CommaSeparatedIdSet::from($printerSet).'/printjobs';

        if ($printJobSet !== null) {
            $endpoint .= '/'.CommaSeparatedIdSet::from($printJobSet);
        }

        return $this->send(new EndpointRequest(Method::DELETE, $endpoint, $childAccount));
    }
}
