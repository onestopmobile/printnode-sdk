<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class PrintJobStateData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public ?string $state,
        public ?string $message,
        public ?string $createTimestamp,
    ) {
        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            attributes: $attributes,
            state: self::stringOrNull($attributes, 'state'),
            message: self::stringOrNull($attributes, 'message'),
            createTimestamp: self::stringOrNull($attributes, 'createTimestamp'),
        );
    }
}
