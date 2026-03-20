<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class AccountData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $childAccounts
     * @param  list<mixed>  $versions
     * @param  list<mixed>  $connected
     * @param  array<string, string>  $tags
     * @param  list<string>  $permissions
     * @param  list<string>  $apiKeys
     */
    private function __construct(
        array $attributes,
        public ?int $id,
        public ?string $firstname,
        public ?string $lastname,
        public ?string $email,
        public ?bool $canCreateSubAccounts,
        public ?string $creatorEmail,
        public ?string $creatorRef,
        public array $childAccounts,
        public ?int $credits,
        public ?int $numComputers,
        public ?int $totalPrints,
        public array $versions,
        public array $connected,
        public array $tags,
        public ?string $state,
        public array $permissions,
        public array $apiKeys,
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
            firstname: self::stringOrNull($attributes, 'firstname'),
            lastname: self::stringOrNull($attributes, 'lastname'),
            email: self::stringOrNull($attributes, 'email'),
            canCreateSubAccounts: self::boolOrNull($attributes, 'canCreateSubAccounts'),
            creatorEmail: self::stringOrNull($attributes, 'creatorEmail'),
            creatorRef: self::stringOrNull($attributes, 'creatorRef'),
            childAccounts: self::intListOrEmpty($attributes, 'childAccounts'),
            credits: self::intOrNull($attributes, 'credits'),
            numComputers: self::intOrNull($attributes, 'numComputers'),
            totalPrints: self::intOrNull($attributes, 'totalPrints'),
            versions: self::listOrEmpty($attributes, 'versions'),
            connected: self::listOrEmpty($attributes, 'connected'),
            tags: self::stringMapOrEmpty($attributes, 'tags', 'Tags'),
            state: self::stringOrNull($attributes, 'state'),
            permissions: self::stringListOrEmpty($attributes, 'permissions'),
            apiKeys: self::stringListOrEmpty($attributes, 'apiKeys', 'ApiKeys'),
        );
    }
}
