<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Enums;

enum AccountState: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
