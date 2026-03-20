<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class ComputerData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public ?int $id,
        public ?string $name,
        public ?string $inet,
        public ?string $inet6,
        public ?string $hostname,
        public ?string $version,
        public ?string $jre,
        public ?string $createTimestamp,
        public ?string $state,
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
            id: self::intOrNull($attributes, 'id'),
            name: self::stringOrNull($attributes, 'name'),
            inet: self::stringOrNull($attributes, 'inet'),
            inet6: self::stringOrNull($attributes, 'inet6'),
            hostname: self::stringOrNull($attributes, 'hostname'),
            version: self::stringOrNull($attributes, 'version'),
            jre: self::stringOrNull($attributes, 'jre'),
            createTimestamp: self::stringOrNull($attributes, 'createTimestamp'),
            state: self::stringOrNull($attributes, 'state'),
        );
    }
}
