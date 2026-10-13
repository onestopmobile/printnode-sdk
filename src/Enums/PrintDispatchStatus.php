<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Enums;

enum PrintDispatchStatus: string
{
    case Sent = 'sent';
    case Skipped = 'skipped';

    /**
     * An earlier attempt with the same idempotency key already reached PrintNode.
     */
    case AlreadySent = 'already_sent';
}
