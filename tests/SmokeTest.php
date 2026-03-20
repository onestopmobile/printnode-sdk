<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Saloon\Http\Auth\BasicAuthenticator;

it('builds a configured Saloon connector', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'printnode-api-key',
        baseUrl: 'https://api.printnode.test/',
        userAgent: 'onestopmobile-printnode-sdk-test/1.0',
        tries: 3,
        retryInterval: 250,
        useExponentialBackoff: true,
        defaultChildAccount: ChildAccountContext::byId(12),
    ));

    $connector = $sdk->connector();
    $authenticator = $connector->getAuthenticator();

    expect($connector->resolveBaseUrl())->toBe('https://api.printnode.test')
        ->and($connector->headers()->all())->toMatchArray([
            'Accept' => 'application/json',
            'User-Agent' => 'onestopmobile-printnode-sdk-test/1.0',
            'X-Child-Account-By-Id' => '12',
        ])
        ->and($authenticator)->toBeInstanceOf(BasicAuthenticator::class)
        ->and($authenticator?->username)->toBe('printnode-api-key')
        ->and($authenticator?->password)->toBe('')
        ->and($connector->tries)->toBe(3)
        ->and($connector->retryInterval)->toBe(250)
        ->and($connector->useExponentialBackoff)->toBeTrue();
});
