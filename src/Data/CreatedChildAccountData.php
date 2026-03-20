<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

final readonly class CreatedChildAccountData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $apiKeys
     * @param  array<string, string>  $tags
     */
    private function __construct(
        array $attributes,
        public ?int $id,
        public ?string $firstname,
        public ?string $lastname,
        public ?string $email,
        public ?string $creatorRef,
        public array $apiKeys,
        public array $tags,
        public ?int $credits,
    ) {
        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $account = self::associativeArrayOrNull($attributes, 'Account', 'account') ?? $attributes;
        $apiKeys = self::stringListOrEmpty($attributes, 'ApiKeys', 'apiKeys');
        $tags = self::stringMapOrEmpty($attributes, 'Tags', 'tags');

        return new self(
            attributes: $attributes,
            id: self::intOrNull($account, 'id'),
            firstname: self::stringOrNull($account, 'firstname'),
            lastname: self::stringOrNull($account, 'lastname'),
            email: self::stringOrNull($account, 'email'),
            creatorRef: self::stringOrNull($account, 'creatorRef'),
            apiKeys: $apiKeys !== [] ? $apiKeys : self::stringListOrEmpty($account, 'ApiKeys', 'apiKeys'),
            tags: $tags !== [] ? $tags : self::stringMapOrEmpty($account, 'Tags', 'tags'),
            credits: self::intOrNull($account, 'credits'),
        );
    }
}
