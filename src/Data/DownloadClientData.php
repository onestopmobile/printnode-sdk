<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class DownloadClientData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        array $attributes,
        public ?int $id,
        public ?string $operatingSystem,
        public ?string $architecture,
        public ?string $version,
        public ?string $filename,
        public ?string $url,
        public ?string $checksum,
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
            operatingSystem: self::stringOrNull($attributes, 'os', 'operatingSystem'),
            architecture: self::stringOrNull($attributes, 'architecture', 'arch'),
            version: self::stringOrNull($attributes, 'version'),
            filename: self::stringOrNull($attributes, 'filename', 'name'),
            url: self::stringOrNull($attributes, 'url'),
            checksum: self::stringOrNull($attributes, 'checksum', 'sha1'),
        );
    }
}
