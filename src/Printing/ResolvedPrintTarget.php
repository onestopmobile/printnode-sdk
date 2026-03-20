<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

final readonly class ResolvedPrintTarget
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $printNodePrinterId,
        public ?string $source = null,
        public array $options = [],
    ) {}

    public static function forPrinter(int $printNodePrinterId): self
    {
        return new self($printNodePrinterId);
    }
}
