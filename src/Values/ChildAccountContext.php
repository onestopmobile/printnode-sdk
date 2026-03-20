<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

final readonly class ChildAccountContext
{
    private function __construct(
        public string $headerName,
        public string $value,
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
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return [
            $this->headerName => $this->value,
        ];
    }
}
