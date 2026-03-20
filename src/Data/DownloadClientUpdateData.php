<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class DownloadClientUpdateData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public ?bool $updated,
        public ?bool $enabled,
        public ?string $message,
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
            updated: self::boolOrNull($attributes, 'updated'),
            enabled: self::boolOrNull($attributes, 'enabled'),
            message: self::stringOrNull($attributes, 'message'),
        );
    }
}
