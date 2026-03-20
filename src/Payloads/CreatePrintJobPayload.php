<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;

final readonly class CreatePrintJobPayload
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $printerId,
        public string $title,
        public PrintContentType $contentType,
        public string $content,
        public string $source = 'PrintNode SDK',
        public ?string $contentTypeHeader = null,
        public ?string $expireAfter = null,
        public ?string $clientKey = null,
        public array $options = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'printerId' => $this->printerId,
            'title' => $this->title,
            'contentType' => $this->contentType->value,
            'content' => $this->content,
            'source' => $this->source,
            'contentTypeHeader' => $this->contentTypeHeader,
            'expireAfter' => $this->expireAfter,
            'clientKey' => $this->clientKey,
            'options' => $this->options === [] ? null : $this->options,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
