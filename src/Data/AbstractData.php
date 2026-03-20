<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Data;

abstract readonly class AbstractData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    protected function __construct(
        private array $raw,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function value(array $attributes, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function intOrNull(array $attributes, string ...$keys): ?int
    {
        $value = self::value($attributes, ...$keys);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function stringOrNull(array $attributes, string ...$keys): ?string
    {
        $value = self::value($attributes, ...$keys);

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function boolOrNull(array $attributes, string ...$keys): ?bool
    {
        $value = self::value($attributes, ...$keys);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    protected static function associativeArrayOrNull(array $attributes, string ...$keys): ?array
    {
        $value = self::value($attributes, ...$keys);

        if (! is_array($value) || array_is_list($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<mixed>
     */
    protected static function listOrEmpty(array $attributes, string ...$keys): array
    {
        $value = self::value($attributes, ...$keys);

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    protected static function intListOrEmpty(array $attributes, string ...$keys): array
    {
        $values = self::listOrEmpty($attributes, ...$keys);
        $normalized = [];

        foreach ($values as $value) {
            if (is_int($value)) {
                $normalized[] = $value;

                continue;
            }

            if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
                $normalized[] = (int) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    protected static function stringListOrEmpty(array $attributes, string ...$keys): array
    {
        $values = self::listOrEmpty($attributes, ...$keys);
        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    protected static function stringMapOrEmpty(array $attributes, string ...$keys): array
    {
        $value = self::value($attributes, ...$keys);

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_string($item)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
