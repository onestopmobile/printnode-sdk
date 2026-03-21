<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

final readonly class CreatedPrintJobResult
{
    public function __construct(
        public int|string $printJobId,
        public ?string $requestId = null,
    ) {}
}
