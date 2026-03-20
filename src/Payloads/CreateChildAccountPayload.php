<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Payloads;

final readonly class CreateChildAccountPayload
{
    /**
     * @param  list<string>  $apiKeys
     * @param  array<string, string>  $tags
     * @param  array<string, mixed>  $extraAccount
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $email,
        public string $password,
        public ?string $creatorRef = null,
        public array $apiKeys = [],
        public array $tags = [],
        public string $firstname = '-',
        public string $lastname = '-',
        public array $extraAccount = [],
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $account = array_filter([
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'password' => $this->password,
            'creatorRef' => $this->creatorRef,
            ...$this->extraAccount,
        ], static fn (mixed $value): bool => $value !== null);

        return array_filter([
            'Account' => $account,
            'ApiKeys' => $this->apiKeys === [] ? null : array_values(array_unique($this->apiKeys)),
            'Tags' => $this->tags === [] ? null : $this->tags,
            ...$this->extra,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
