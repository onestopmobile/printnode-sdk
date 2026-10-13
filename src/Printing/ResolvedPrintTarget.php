<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

final readonly class ResolvedPrintTarget
{
    /**
     * @param  array<string, mixed>  $options
     * @param  ?ChildAccountContext  $childAccount  The child account that owns the printer. Leave it
     *                                              null to use the connector's default account.
     */
    public function __construct(
        public int $printNodePrinterId,
        public ?string $source = null,
        public array $options = [],
        public ?ChildAccountContext $childAccount = null,
    ) {}

    public static function forPrinter(int $printNodePrinterId): self
    {
        return new self($printNodePrinterId);
    }
}
