<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use Psr\Log\LoggerInterface;

final readonly class PrintManagerConfig
{
    /**
     * @param  array<string, mixed>  $defaultOptions
     */
    public function __construct(
        public ?ResolvesPrintTarget $targetResolver = null,
        public string $defaultTitle = 'Print job',
        public string $defaultSource = 'PrintNode SDK',
        public array $defaultOptions = [],
        public ?string $defaultIdempotencyPrefix = null,
        public ?DecidesPrintDispatch $dispatchPolicy = null,
        public ?LoggerInterface $logger = null,
        public bool $logSkipped = true,
        public bool $logSuccess = false,
        public bool $logFailures = true,
        public bool $includeContentHashInLogs = false,
        public bool $includeContentLengthInLogs = true,
    ) {}
}
