<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class PrintJobData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public int|string|null $id,
        public ?PrinterData $printer,
        public ?int $printerId,
        public ?string $title,
        public ?string $contentType,
        public ?string $source,
        public ?string $expireAt,
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
        $printer = self::associativeArrayOrNull($attributes, 'printer');
        $id = self::intOrNull($attributes, 'id');

        if ($id === null) {
            $stringId = self::stringOrNull($attributes, 'id');
            $id = $stringId === null || $stringId === '' ? null : $stringId;
        }

        return new self(
            attributes: $attributes,
            id: $id,
            printer: $printer === null ? null : PrinterData::fromArray($printer),
            printerId: is_array($printer) ? self::intOrNull($printer, 'id') : null,
            title: self::stringOrNull($attributes, 'title'),
            contentType: self::stringOrNull($attributes, 'contentType'),
            source: self::stringOrNull($attributes, 'source'),
            expireAt: self::stringOrNull($attributes, 'expireAt'),
            createTimestamp: self::stringOrNull($attributes, 'createTimestamp'),
            state: self::stringOrNull($attributes, 'state'),
        );
    }
}
