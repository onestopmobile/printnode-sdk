<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Behaves like Laravel's LogManager: it is a logger itself (writing to the default channel), and
 * channel() hands out named loggers.
 */
final class FakeLogManager extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $requestedChannels = [];

    /**
     * @var array<string, LoggerInterface>
     */
    private array $channels = [];

    public function __construct(
        private readonly LoggerInterface $defaultLogger,
    ) {}

    public function channel(string $name): LoggerInterface
    {
        $this->requestedChannels[] = $name;

        return $this->channels[$name] ??= $this->defaultLogger;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->defaultLogger->log($level, $message, $context);
    }
}
