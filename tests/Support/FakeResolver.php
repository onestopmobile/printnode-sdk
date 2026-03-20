<?php

declare(strict_types=1);

namespace Tests\Support;

use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Printing\ResolvedPrintTarget;

final class FakeResolver implements ResolvesPrintTarget
{
    public function resolve(mixed $target): ResolvedPrintTarget
    {
        return new ResolvedPrintTarget(
            printNodePrinterId: 999,
            source: is_string($target) ? 'resolver:'.$target : 'resolver',
        );
    }
}
