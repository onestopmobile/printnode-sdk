<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

final readonly class UpdateChildAccountPayload
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
        public ?string $firstname = null,
        public ?string $lastname = null,
        public ?string $creatorRef = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'password' => $this->password,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'creatorRef' => $this->creatorRef,
            ...$this->extra,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
