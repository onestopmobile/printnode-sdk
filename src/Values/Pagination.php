<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

use OneStopMobile\PrintNodeSdk\Enums\SortDirection;

final readonly class Pagination
{
    public function __construct(
        public ?int $limit = null,
        public ?int $after = null,
        public ?SortDirection $direction = null,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'limit' => $this->limit,
            'after' => $this->after,
            'dir' => $this->direction?->value,
        ], static fn (int|string|null $value): bool => $value !== null);
    }
}
