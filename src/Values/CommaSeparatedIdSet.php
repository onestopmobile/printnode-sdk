<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use Stringable;

final readonly class CommaSeparatedIdSet implements Stringable
{
    /**
     * @param  list<int>  $ids
     */
    private function __construct(
        public array $ids,
    ) {}

    /**
     * @param  int|array<mixed>|self|string  $value
     */
    public static function from(int|array|self|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value)) {
            return new self([$value]);
        }

        if (is_string($value)) {
            return self::fromNormalizedIds(array_values(array_unique(array_filter(array_map(
                static function (string $part): ?int {
                    $part = trim($part);

                    return $part !== '' && is_numeric($part) ? (int) $part : null;
                },
                explode(',', $value),
            ), static fn (?int $id): bool => $id !== null))));
        }

        return self::fromNormalizedIds(array_values(array_unique(array_filter(array_map(
            static function (mixed $id): ?int {
                if (is_int($id)) {
                    return $id;
                }

                if (is_string($id)) {
                    $id = trim($id);

                    return $id !== '' && is_numeric($id) ? (int) $id : null;
                }

                return null;
            },
            $value,
        ), static fn (?int $id): bool => $id !== null))));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function fromNormalizedIds(array $ids): self
    {
        if ($ids === []) {
            throw new InvalidIdentifierSetException('The identifier set must contain at least one numeric identifier.');
        }

        return new self($ids);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function __toString(): string
    {
        return implode(',', $this->ids);
    }
}
