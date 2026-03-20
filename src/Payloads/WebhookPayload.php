<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

final readonly class WebhookPayload
{
    /**
     * @param  list<string>|null  $messages
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $url,
        public ?array $messages = null,
        public ?string $secret = null,
        public bool $enabled = true,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'url' => $this->url,
            'messages' => $this->messages,
            'secret' => $this->secret,
            'enabled' => $this->enabled,
            ...$this->extra,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
