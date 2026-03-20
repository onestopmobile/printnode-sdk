<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Log\LoggerInterface;

final class FakeLogManager
{
    /**
     * @var array<string, LoggerInterface>
     */
    private array $channels = [];

    public function __construct(
        private readonly LoggerInterface $defaultLogger,
    ) {}

    public function channel(string $name): LoggerInterface
    {
        return $this->channels[$name] ??= $this->defaultLogger;
    }
}
