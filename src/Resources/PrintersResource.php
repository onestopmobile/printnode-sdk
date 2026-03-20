<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\PrinterData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\CommaSeparatedIdSet;
use Saloon\Enums\Method;

final readonly class PrintersResource extends AbstractResource
{
    /**
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $printerSet
     * @param  int|array<int>|CommaSeparatedIdSet|string|null  $computerSet
     * @return list<PrinterData>
     */
    public function all(
        int|array|CommaSeparatedIdSet|string|null $printerSet = null,
        int|array|CommaSeparatedIdSet|string|null $computerSet = null,
        ?ChildAccountContext $childAccount = null,
    ): array {
        $endpoint = $computerSet === null
            ? '/printers'
            : '/computers/'.CommaSeparatedIdSet::from($computerSet).'/printers';

        if ($printerSet !== null) {
            $endpoint .= '/'.CommaSeparatedIdSet::from($printerSet);
        }

        /** @var list<array<string, mixed>> $printers */
        $printers = $this->send(new EndpointRequest(Method::GET, $endpoint, $childAccount));

        return array_map(PrinterData::fromArray(...), $printers);
    }
}
