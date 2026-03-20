<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Enums;

enum OperatingSystem: string
{
    case Linux = 'linux';
    case MacOs = 'osx';
    case Windows = 'windows';
}
