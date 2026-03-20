<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Contracts;

use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchContext;
use OneStopMobile\PrintNodeSdk\Printing\PrintDispatchDecision;

interface DecidesPrintDispatch
{
    public function decide(PrintDispatchContext $context): PrintDispatchDecision;
}
