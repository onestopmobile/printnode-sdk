<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Enums;

enum PrintDispatchAction: string
{
    case Send = 'send';
    case Skip = 'skip';
    case Fail = 'fail';
}
