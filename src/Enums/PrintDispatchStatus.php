<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Enums;

enum PrintDispatchStatus: string
{
    case Sent = 'sent';
    case Skipped = 'skipped';
}
