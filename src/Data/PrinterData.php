<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class PrinterData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $capabilities
     */
    private function __construct(
        array $attributes,
        public ?int $id,
        public ?ComputerData $computer,
        public ?int $computerId,
        public ?string $name,
        public ?string $description,
        public ?array $capabilities,
        public ?bool $default,
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
        $computer = self::associativeArrayOrNull($attributes, 'computer');

        return new self(
            attributes: $attributes,
            id: self::intOrNull($attributes, 'id'),
            computer: $computer === null ? null : ComputerData::fromArray($computer),
            computerId: is_array($computer) ? self::intOrNull($computer, 'id') : null,
            name: self::stringOrNull($attributes, 'name'),
            description: self::stringOrNull($attributes, 'description'),
            capabilities: self::associativeArrayOrNull($attributes, 'capabilities'),
            default: self::boolOrNull($attributes, 'default'),
            createTimestamp: self::stringOrNull($attributes, 'createTimestamp'),
            state: self::stringOrNull($attributes, 'state'),
        );
    }
}
