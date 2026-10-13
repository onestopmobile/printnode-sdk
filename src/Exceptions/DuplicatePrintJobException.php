<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Exceptions;

/**
 * PrintNode already accepted a print job with this idempotency key (keys are remembered for 24
 * hours), so it refused to create the same job again. Nothing went missing: the earlier attempt
 * reached PrintNode. PrintNode does not tell us the id of that earlier job.
 */
final class DuplicatePrintJobException extends ConflictException
{
    public function __construct(
        public readonly string $idempotencyKey,
        ConflictException $conflict,
    ) {
        parent::__construct(
            sprintf('PrintNode already accepted a print job with idempotency key "%s".', $idempotencyKey),
            $conflict->status,
            $conflict->requestId,
            $conflict->payload,
        );
    }
}
