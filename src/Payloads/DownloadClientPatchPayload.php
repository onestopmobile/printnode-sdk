<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

final readonly class DownloadClientPatchPayload
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?bool $enabled = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'enabled' => $this->enabled,
            ...$this->extra,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
