<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Resources;

use OneStopMobile\PrintNodeSdk\Data\AccountData;
use OneStopMobile\PrintNodeSdk\Data\CreatedChildAccountData;
use OneStopMobile\PrintNodeSdk\Enums\AccountState;
use OneStopMobile\PrintNodeSdk\Http\Requests\EndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Requests\StringBodyEndpointRequest;
use OneStopMobile\PrintNodeSdk\Payloads\CreateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\Payloads\UpdateChildAccountPayload;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Enums\Method;

final readonly class AccountResource extends AbstractResource
{
    public function create(CreateChildAccountPayload $payload): CreatedChildAccountData
    {
        return CreatedChildAccountData::fromArray(
            $this->mapResponse(
                $this->send(new JsonEndpointRequest(Method::POST, '/account', $payload->toArray())),
                'POST /account',
            ),
        );
    }

    public function update(UpdateChildAccountPayload $payload, ?ChildAccountContext $childAccount = null): AccountData
    {
        return AccountData::fromArray(
            $this->mapResponse(
                $this->send(new JsonEndpointRequest(Method::PATCH, '/account', $payload->toArray(), $childAccount)),
                'PATCH /account',
            ),
        );
    }

    public function delete(?ChildAccountContext $childAccount = null): void
    {
        $this->send(new EndpointRequest(Method::DELETE, '/account', $childAccount));
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
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::GET, '/account/state', $childAccount)),
            'GET /account/state',
        );
    }

    public function setState(AccountState|string $state, ?ChildAccountContext $childAccount = null): void
    {
        $value = $state instanceof AccountState ? $state->value : $state;

        $this->send(new StringBodyEndpointRequest(
            Method::PUT,
            '/account/state',
            json_encode($value, JSON_THROW_ON_ERROR),
            'application/json',
            $childAccount,
        ));
    }

    public function getTag(string $name, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::GET, '/account/tag/'.rawurlencode($name), $childAccount)),
            'GET /account/tag',
        );
    }

    public function setTag(string $name, string $value, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new StringBodyEndpointRequest(
                Method::POST,
                '/account/tag/'.rawurlencode($name),
                $value,
                'text/plain',
                $childAccount,
            )),
            'POST /account/tag',
        );
    }

    public function deleteTag(string $name, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::DELETE, '/account/tag/'.rawurlencode($name), $childAccount)),
            'DELETE /account/tag',
        );
    }

    public function getApiKey(string $description, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::GET, '/account/apikey/'.rawurlencode($description), $childAccount)),
            'GET /account/apikey',
        );
    }

    public function createApiKey(string $description, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::POST, '/account/apikey/'.rawurlencode($description), $childAccount)),
            'POST /account/apikey',
        );
    }

    public function deleteApiKey(string $description, ?ChildAccountContext $childAccount = null): string
    {
        return $this->stringResponse(
            $this->send(new EndpointRequest(Method::DELETE, '/account/apikey/'.rawurlencode($description), $childAccount)),
            'DELETE /account/apikey',
        );
    }

    public function clientKey(
        string $key,
        ?string $version = null,
        ?string $edition = null,
        ?ChildAccountContext $childAccount = null,
    ): string {
        return $this->stringResponse(
            $this->send(new EndpointRequest(
                Method::GET,
                '/client/key/'.rawurlencode($key),
                $childAccount,
                array_filter([
                    'version' => $version,
                    'edition' => $edition,
                ], static fn (?string $value): bool => $value !== null),
            )),
            'GET /client/key',
        );
    }
}
