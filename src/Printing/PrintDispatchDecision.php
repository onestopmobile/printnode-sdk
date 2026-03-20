<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;

final readonly class PrintDispatchDecision
{
    private function __construct(
        public PrintDispatchAction $action,
        public ?string $reason = null,
    ) {}

    public static function send(): self
    {
        return new self(PrintDispatchAction::Send);
    }

    public static function skip(?string $reason = null): self
    {
        return new self(PrintDispatchAction::Skip, $reason);
    }

    public static function fail(?string $reason = null): self
    {
        return new self(PrintDispatchAction::Fail, $reason);
    }
}
