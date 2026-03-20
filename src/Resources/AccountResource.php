<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\AccountData;
use OneStopMobile\PrintNodeSdk\Enums\AccountState;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\StringBodyEndpointRequest;
use OneStopMobile\PrintNodeSdk\Payloads\CreateAccountPayload;
use OneStopMobile\PrintNodeSdk\Payloads\UpdateAccountPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class AccountResource extends AbstractResource
{
    public function create(CreateAccountPayload $payload, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new JsonEndpointRequest(Method::POST, '/account', $payload->toArray(), $childAccount));
    }

    public function update(UpdateAccountPayload $payload, ?ChildAccountContext $childAccount = null): AccountData
    {
        /** @var array<string, mixed> $account */
        $account = $this->send(new JsonEndpointRequest(Method::PATCH, '/account', $payload->toArray(), $childAccount));

        return AccountData::fromArray($account);
    }

    public function delete(?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::DELETE, '/account', $childAccount));
    }

    /**
     * @return list<AccountData>
     */
    public function controllable(?ChildAccountContext $childAccount = null): array
    {
        /** @var list<array<string, mixed>> $accounts */
        $accounts = $this->send(new EndpointRequest(Method::GET, '/account/controllable', $childAccount));

        return array_map(AccountData::fromArray(...), $accounts);
    }

    public function getState(?ChildAccountContext $childAccount = null): string
    {
        /** @var string $state */
        $state = $this->send(new EndpointRequest(Method::GET, '/account/state', $childAccount));

        return $state;
    }

    public function setState(AccountState|string $state, ?ChildAccountContext $childAccount = null): mixed
    {
        $value = $state instanceof AccountState ? $state->value : $state;

        return $this->send(new StringBodyEndpointRequest(
            Method::PUT,
            '/account/state',
            json_encode($value, JSON_THROW_ON_ERROR),
            'application/json',
            $childAccount,
        ));
    }

    public function getTag(string $name, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::GET, '/account/tag/'.rawurlencode($name), $childAccount));
    }

    public function setTag(string $name, string $value, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new StringBodyEndpointRequest(
            Method::POST,
            '/account/tag/'.rawurlencode($name),
            $value,
            'text/plain',
            $childAccount,
        ));
    }

    public function deleteTag(string $name, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::DELETE, '/account/tag/'.rawurlencode($name), $childAccount));
    }

    public function getApiKey(string $description, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::GET, '/account/apikey/'.rawurlencode($description), $childAccount));
    }

    public function createApiKey(string $description, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::POST, '/account/apikey/'.rawurlencode($description), $childAccount));
    }

    public function deleteApiKey(string $description, ?ChildAccountContext $childAccount = null): mixed
    {
        return $this->send(new EndpointRequest(Method::DELETE, '/account/apikey/'.rawurlencode($description), $childAccount));
    }

    public function clientKey(
        string $key,
        ?string $version = null,
        ?string $edition = null,
        ?ChildAccountContext $childAccount = null,
    ): mixed {
        return $this->send(new EndpointRequest(
            Method::GET,
            '/client/key/'.rawurlencode($key),
            $childAccount,
            array_filter([
                'version' => $version,
                'edition' => $edition,
            ], static fn (?string $value): bool => $value !== null),
        ));
    }
}
