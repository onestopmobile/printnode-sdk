<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Contracts;

use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;

interface ResolvesPrintTarget
{
    public function resolve(mixed $target): ResolvedPrintTarget;
}
