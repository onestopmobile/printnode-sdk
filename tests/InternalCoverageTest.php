<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Data\AbstractData;
use OneStopMobile\PrintNodeSdk\Data\PrintJobData;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Resources\AbstractResource;

final readonly class AbstractDataProbe extends AbstractData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromRaw(array $raw): self
    {
        return new self($raw);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function intOrNullValue(array $attributes, string ...$keys): ?int
    {
        return self::intOrNull($attributes, ...$keys);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function boolOrNullValue(array $attributes, string ...$keys): ?bool
    {
        return self::boolOrNull($attributes, ...$keys);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public static function associativeArrayOrNullValue(array $attributes, string ...$keys): ?array
    {
        return self::associativeArrayOrNull($attributes, ...$keys);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    public static function intListOrEmptyValue(array $attributes, string ...$keys): array
    {
        return self::intListOrEmpty($attributes, ...$keys);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public static function stringMapOrEmptyValue(array $attributes, string ...$keys): array
    {
        return self::stringMapOrEmpty($attributes, ...$keys);
    }
}

final readonly class AbstractResourceProbe extends AbstractResource
{
    public function stringValue(mixed $payload, string $context): string
    {
        return $this->stringResponse($payload, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapValue(mixed $payload, string $context): array
    {
        return $this->mapResponse($payload, $context);
    }

    /**
     * @return list<int|string>
     */
    public function identifierListValue(mixed $payload, string $context): array
    {
        return $this->identifierListResponse($payload, $context);
    }

    public function printJobIdValue(mixed $payload): int|string
    {
        return $this->printJobIdResponse($payload);
    }
}

function resourceProbe(): AbstractResourceProbe
{
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test',
    ));

    return new AbstractResourceProbe($sdk->connector());
}

it('covers abstract data helpers with raw payload access', function (): void {
    $raw = [
        'id' => '42',
        'flagTrue' => 1,
        'flagFalse' => 0,
        'meta' => [
            1 => 'ignore-numeric-key',
            'keep' => 'value',
        ],
        'ids' => [5, '6', 'ignore-me'],
        'tags' => [
            1 => 'ignore-numeric-key',
            'ignore' => 2,
            'keep' => 'value',
        ],
    ];
    $probe = AbstractDataProbe::fromRaw($raw);

    expect($probe->raw())->toBe($raw)
        ->and(AbstractDataProbe::intOrNullValue($raw, 'id'))->toBe(42)
        ->and(AbstractDataProbe::boolOrNullValue($raw, 'flagTrue'))->toBeTrue()
        ->and(AbstractDataProbe::boolOrNullValue($raw, 'flagFalse'))->toBeFalse()
        ->and(AbstractDataProbe::associativeArrayOrNullValue($raw, 'meta'))->toBe([
            'keep' => 'value',
        ])
        ->and(AbstractDataProbe::intListOrEmptyValue($raw, 'ids'))->toBe([5, 6])
        ->and(AbstractDataProbe::stringMapOrEmptyValue($raw, 'tags'))->toBe([
            'keep' => 'value',
        ]);
});

it('covers abstract resource response helpers for valid payloads', function (): void {
    $probe = resourceProbe();

    expect($probe->stringValue(12.5, 'numeric payload'))->toBe('12.5')
        ->and($probe->mapValue(['ok' => true], 'map payload'))->toBe([
            'ok' => true,
        ])
        ->and($probe->identifierListValue(['7', 'job-uuid'], 'identifier payload'))->toBe([7, 'job-uuid'])
        ->and($probe->printJobIdValue('88'))->toBe(88)
        ->and($probe->printJobIdValue([
            'data' => [
                'id' => '99',
            ],
        ]))->toBe(99)
        ->and($probe->printJobIdValue([
            'id' => 'job-uuid',
        ]))->toBe('job-uuid');
});

it('covers abstract resource response helper failures', function (): void {
    $probe = resourceProbe();

    expect(fn (): string => $probe->stringValue(['bad' => true], 'string payload'))
        ->toThrow(UnexpectedValueException::class, 'Expected string payload to return a string response.');

    expect(fn (): array => $probe->mapValue(['bad'], 'map payload'))
        ->toThrow(UnexpectedValueException::class, 'Expected map payload to return an object-like response payload.');

    expect(fn (): array => $probe->identifierListValue('bad', 'identifier payload'))
        ->toThrow(UnexpectedValueException::class, 'Expected identifier payload to return a list of identifiers.');

    expect(fn (): array => $probe->identifierListValue([null], 'identifier payload'))
        ->toThrow(UnexpectedValueException::class, 'Expected identifier payload to contain only scalar identifiers.');

    expect(fn (): int|string => $probe->printJobIdValue(null))
        ->toThrow(UnexpectedValueException::class, 'Expected the PrintNode API to return a recognizable print job identifier.');
});

it('preserves non-numeric print job identifiers', function (): void {
    $printJob = PrintJobData::fromArray([
        'id' => 'job-uuid',
        'title' => 'Shipping Label',
        'printer' => [
            'id' => 42,
            'name' => 'Brother QL',
        ],
    ]);

    expect($printJob->id)->toBe('job-uuid')
        ->and($printJob->printerId)->toBe(42)
        ->and($printJob->printer?->id)->toBe(42)
        ->and($printJob->title)->toBe('Shipping Label');
});
