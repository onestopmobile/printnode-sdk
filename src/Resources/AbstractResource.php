<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Http\PrintNodeConnector;
use OneStopMobile\PrintNodeSdk\Http\Requests\AbstractPrintNodeRequest;
use OneStopMobile\PrintNodeSdk\Http\Responses\PrintNodeResponse;

abstract readonly class AbstractResource
{
    public function __construct(
        protected PrintNodeConnector $connector,
    ) {}

    protected function send(AbstractPrintNodeRequest $request): mixed
    {
        /** @var PrintNodeResponse $response */
        $response = $this->connector->send($request);

        return $response->dtoOrThrow();
    }
}
