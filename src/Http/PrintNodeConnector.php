<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Http;

use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;
use Saloon\Traits\RequestProperties\HasTries;

final class PrintNodeConnector extends Connector
{
    use HasTimeout;
    use HasTries;

    public float $connectTimeout;

    public float $requestTimeout;

    public function __construct(
        private readonly PrintNodeConfig $printNodeConfig,
    ) {
        $this->connectTimeout = $printNodeConfig->connectTimeout;
        $this->requestTimeout = $printNodeConfig->requestTimeout;
        $this->tries = $printNodeConfig->tries;
        $this->retryInterval = $printNodeConfig->retryInterval;
        $this->useExponentialBackoff = $printNodeConfig->useExponentialBackoff;
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->printNodeConfig->baseUrl, '/');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => $this->printNodeConfig->userAgent,
            ...($this->printNodeConfig->defaultChildAccount?->toHeaders() ?? []),
        ];
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator($this->printNodeConfig->apiKey, '');
    }
}
