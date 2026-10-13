<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Values;

use OneStopMobile\PrintNodeSdk\Exceptions\InvalidIdentifierSetException;
use Stringable;

/**
 * A set of PrintNode ids for a URL like /printjobs/12,13. These sets also end up in DELETE requests,
 * so every part must be a valid id: a typo fails loudly instead of being dropped or turned into a
 * different id.
 */
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

        $parts = match (true) {
            is_int($value) => [$value],
            is_string($value) => trim($value) === '' ? [] : explode(',', $value),
            default => $value,
        };

        if ($parts === []) {
            throw new InvalidIdentifierSetException('The identifier set must contain at least one identifier.');
        }

        return new self(array_values(array_unique(array_map(self::toId(...), $parts))));
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function __toString(): string
    {
        return implode(',', $this->ids);
    }

    private static function toId(mixed $part): int
    {
        if (is_string($part)) {
            $part = trim($part);

            if (ctype_digit($part)) {
                $part = (int) $part;
            }
        }

        if (is_int($part) && $part > 0) {
            return $part;
        }

        $shown = match (true) {
            is_string($part) => '"'.$part.'"',
            is_int($part), is_float($part) => (string) $part,
            default => get_debug_type($part),
        };

        throw new InvalidIdentifierSetException($shown.' is not a PrintNode id. Ids are positive whole numbers.');
    }
}
