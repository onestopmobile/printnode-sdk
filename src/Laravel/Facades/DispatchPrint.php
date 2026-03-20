<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;

final class DispatchPrint extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PrintManager::class;
    }
}
