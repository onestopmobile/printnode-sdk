<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class ScaleTestResultData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public ?bool $ok,
        public ?int $computerId,
        public ?string $deviceName,
        public ?int $deviceNumber,
        public ?int $mass,
        public ?string $unit,
        public ?bool $stable,
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
            ok: self::boolOrNull($attributes, 'ok'),
            computerId: self::intOrNull($attributes, 'computer'),
            deviceName: self::stringOrNull($attributes, 'deviceName'),
            deviceNumber: self::intOrNull($attributes, 'deviceNum', 'deviceNumber'),
            mass: self::intOrNull($attributes, 'mass'),
            unit: self::stringOrNull($attributes, 'unit'),
            stable: self::boolOrNull($attributes, 'stable'),
            message: self::stringOrNull($attributes, 'message'),
        );
    }
}
