<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchStatus;

final readonly class PrintResult
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int|string|null $printJobId,
        public int $printNodePrinterId,
        public string $title,
        public PrintContentType $contentType,
        public ?string $source = null,
        public ?string $requestId = null,
        public ?string $idempotencyKey = null,
        public array $options = [],
        public PrintDispatchStatus $status = PrintDispatchStatus::Sent,
    ) {}

    public function wasSent(): bool
    {
        return $this->status === PrintDispatchStatus::Sent;
    }

    public function wasSkipped(): bool
    {
        return $this->status === PrintDispatchStatus::Skipped;
    }

    /**
     * PrintNode already had this job from an earlier attempt with the same idempotency key, for
     * example a queued job that is being retried. The print is not lost, it was just not repeated.
     * PrintNode does not return the earlier job's id, so printJobId is null.
     */
    public function wasAlreadySent(): bool
    {
        return $this->status === PrintDispatchStatus::AlreadySent;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function skipped(
        int $printNodePrinterId,
        string $title,
        PrintContentType $contentType,
        ?string $source = null,
        ?string $idempotencyKey = null,
        array $options = [],
    ): self {
        return new self(
            printJobId: null,
            printNodePrinterId: $printNodePrinterId,
            title: $title,
            contentType: $contentType,
            source: $source,
            idempotencyKey: $idempotencyKey,
            options: $options,
            status: PrintDispatchStatus::Skipped,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function alreadySent(
        int $printNodePrinterId,
        string $title,
        PrintContentType $contentType,
        string $idempotencyKey,
        ?string $source = null,
        ?string $requestId = null,
        array $options = [],
    ): self {
        return new self(
            printJobId: null,
            printNodePrinterId: $printNodePrinterId,
            title: $title,
            contentType: $contentType,
            source: $source,
            requestId: $requestId,
            idempotencyKey: $idempotencyKey,
            options: $options,
            status: PrintDispatchStatus::AlreadySent,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function sent(
        int|string $printJobId,
        int $printNodePrinterId,
        string $title,
        PrintContentType $contentType,
        ?string $source = null,
        ?string $requestId = null,
        ?string $idempotencyKey = null,
        array $options = [],
    ): self {
        return new self(
            printJobId: $printJobId,
            printNodePrinterId: $printNodePrinterId,
            title: $title,
            contentType: $contentType,
            source: $source,
            requestId: $requestId,
            idempotencyKey: $idempotencyKey,
            options: $options,
            status: PrintDispatchStatus::Sent,
        );
    }
}
