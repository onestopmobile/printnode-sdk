<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

final class PendingPrint
{
    private ?int $printerId = null;

    private mixed $target = null;

    private ?PrintContentType $contentType = null;

    private ?string $content = null;

    private ?string $title = null;

    private ?string $source = null;

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    private ?string $idempotencyKey = null;

    private ?string $contentTypeHeader = null;

    private ?int $expireAfter = null;

    private ?int $qty = null;

    private ?string $clientKey = null;

    private ?ChildAccountContext $childAccount = null;

    private function __construct(
        private readonly PrintManager $manager,
    ) {}

    public static function forPrinter(PrintManager $manager, int $printerId): self
    {
        $self = new self($manager);
        $self->printerId = $printerId;

        return $self;
    }

    public static function forTarget(PrintManager $manager, mixed $target): self
    {
        $self = new self($manager);
        $self->target = $target;

        return $self;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function source(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $this->options = [
            ...$this->options,
            ...$options,
        ];

        return $this;
    }

    public function idempotencyKey(string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function contentTypeHeader(string $contentTypeHeader): self
    {
        $this->contentTypeHeader = $contentTypeHeader;

        return $this;
    }

    /**
     * Drop the job if it could not be printed within this many seconds. Useful for receipts: one that
     * prints hours later, when the printer's computer comes back online, only confuses people.
     */
    public function expireAfter(int $seconds): self
    {
        $this->expireAfter = $seconds;

        return $this;
    }

    /**
     * Print the job this many times. Unlike the 'copies' option, this also works for raw jobs.
     */
    public function qty(int $qty): self
    {
        $this->qty = $qty;

        return $this;
    }

    public function clientKey(string $clientKey): self
    {
        $this->clientKey = $clientKey;

        return $this;
    }

    /**
     * Send the job from this child account. Wins over the child account of a resolved target.
     */
    public function childAccount(ChildAccountContext $childAccount): self
    {
        $this->childAccount = $childAccount;

        return $this;
    }

    public function pdfUrl(string $url, ?string $title = null): PrintResult
    {
        return $this->withContent(PrintContentType::PdfUri, $url, $title)->send();
    }

    public function pdfBase64(string $content, ?string $title = null): PrintResult
    {
        return $this->withContent(PrintContentType::PdfBase64, $content, $title)->send();
    }

    public function raw(string $content, ?string $title = null): PrintResult
    {
        return $this->withContent(PrintContentType::RawBase64, base64_encode($content), $title)->send();
    }

    public function zpl(string $content, ?string $title = null): PrintResult
    {
        return $this->raw($content, $title);
    }

    public function rawBase64(string $content, ?string $title = null): PrintResult
    {
        return $this->withContent(PrintContentType::RawBase64, $content, $title)->send();
    }

    public function zplBase64(string $content, ?string $title = null): PrintResult
    {
        return $this->rawBase64($content, $title);
    }

    public function send(): PrintResult
    {
        return $this->manager->dispatch($this);
    }

    public function printerId(): ?int
    {
        return $this->printerId;
    }

    public function target(): mixed
    {
        return $this->target;
    }

    public function contentType(): ?PrintContentType
    {
        return $this->contentType;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function configuredTitle(): ?string
    {
        return $this->title;
    }

    public function configuredSource(): ?string
    {
        return $this->source;
    }

    /**
     * @return array<string, mixed>
     */
    public function optionValues(): array
    {
        return $this->options;
    }

    public function configuredIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function configuredContentTypeHeader(): ?string
    {
        return $this->contentTypeHeader;
    }

    public function configuredExpireAfter(): ?int
    {
        return $this->expireAfter;
    }

    public function configuredQty(): ?int
    {
        return $this->qty;
    }

    public function configuredClientKey(): ?string
    {
        return $this->clientKey;
    }

    public function configuredChildAccount(): ?ChildAccountContext
    {
        return $this->childAccount;
    }

    private function withContent(PrintContentType $contentType, string $content, ?string $title): self
    {
        $this->contentType = $contentType;
        $this->content = $content;

        if ($title !== null) {
            $this->title($title);
        }

        return $this;
    }
}
