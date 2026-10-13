<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

final readonly class ChildAccountContext
{
    /**
     * The headers PrintNode reads to pick a child account. A request carries at most one of them.
     */
    public const array HEADER_NAMES = [
        'X-Child-Account-By-Id',
        'X-Child-Account-By-Email',
        'X-Child-Account-By-CreatorRef',
    ];

    private function __construct(
        public ?string $headerName,
        public ?string $value,
    ) {}

    public static function byId(int $id): self
    {
        return new self('X-Child-Account-By-Id', (string) $id);
    }

    public static function byEmail(string $email): self
    {
        return new self('X-Child-Account-By-Email', $email);
    }

    public static function byCreatorRef(string $creatorRef): self
    {
        return new self('X-Child-Account-By-CreatorRef', $creatorRef);
    }

    /**
     * The integrator (parent) account itself. Use this for calls that must not run inside a child
     * account, even when a default child account is configured on the connector.
     */
    public static function parentAccount(): self
    {
        return new self(null, null);
    }

    public function isParentAccount(): bool
    {
        return $this->headerName === null;
    }

    /**
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        if ($this->headerName === null || $this->value === null) {
            return [];
        }

        return [
            $this->headerName => $this->value,
        ];
    }
}
