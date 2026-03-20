<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;

final readonly class PrintDispatchContext
{
    public function __construct(
        public PendingPrint $pendingPrint,
        public ResolvedPrintTarget $target,
        public CreatePrintJobPayload $payload,
        public ?string $idempotencyKey = null,
    ) {}
}
