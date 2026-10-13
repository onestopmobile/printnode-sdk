<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;

final readonly class CreatePrintJobPayload
{
    /**
     * @param  ?int  $expireAfter  Seconds PrintNode keeps the job when it cannot print right away
     *                             (the printer's computer is offline, say). PrintNode's default is
     *                             14 days, which is long for a receipt.
     * @param  array<string, mixed>  $options
     * @param  ?int  $qty  How many times PrintNode sends the job to the printer. Unlike the
     *                     'copies' option, this also works for raw jobs such as ESC/POS or ZPL.
     */
    public function __construct(
        public int $printerId,
        public string $title,
        public PrintContentType $contentType,
        public string $content,
        public string $source = 'PrintNode SDK',
        public ?string $contentTypeHeader = null,
        public ?int $expireAfter = null,
        public ?string $clientKey = null,
        public array $options = [],
        public ?int $qty = null,
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
            'qty' => $this->qty,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
