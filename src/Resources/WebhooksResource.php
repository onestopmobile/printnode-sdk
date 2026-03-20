<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\WebhookData;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Payloads\WebhookPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class WebhooksResource extends AbstractResource
{
    /**
     * @return list<WebhookData>
     */
    public function all(?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $webhooks */
        $webhooks = $this->send(new EndpointRequest(Method::GET, '/webhooks', $childAccount));

        return array_map(WebhookData::fromArray(...), $webhooks);
    }

    public function create(WebhookPayload $payload, ?ChildAccountContext $childAccount = null): WebhookData
    {
        /** @var array<string, mixed> $webhook */
        $webhook = $this->send(new JsonEndpointRequest(Method::POST, '/webhook', $payload->toArray(), $childAccount));

        return WebhookData::fromArray($webhook);
    }

    public function update(int $id, WebhookPayload $payload, ?ChildAccountContext $childAccount = null): WebhookData
    {
        /** @var array<string, mixed> $webhook */
        $webhook = $this->send(new JsonEndpointRequest(Method::PATCH, sprintf('/webhook/%d', $id), $payload->toArray(), $childAccount));

        return WebhookData::fromArray($webhook);
    }

    public function delete(int $id, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::DELETE, sprintf('/webhook/%d', $id), $childAccount));
    }
}
