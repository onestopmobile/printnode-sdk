<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

final readonly class PrintNodeConfig
{
    public function __construct(
        public string $apiKey,
        public string $baseUrl = 'https://api.printnode.com',
        public string $userAgent = 'onestopmobile-printnode-sdk',
        public float $connectTimeout = 10,
        public float $requestTimeout = 30,
        public int $tries = 1,
        public int $retryInterval = 0,
        public bool $useExponentialBackoff = false,
        public ?ChildAccountContext $defaultChildAccount = null,
    ) {}
}
