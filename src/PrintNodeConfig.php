<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk;

use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;

final readonly class PrintNodeConfig
{
    /**
     * @param  int  $tries  How many attempts a request gets. Only reads and print jobs with an
     *                      idempotency key are ever repeated (see PrintNodeConnector::handleRetry()).
     * @param  int  $retryInterval  Milliseconds to wait between attempts.
     */
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
