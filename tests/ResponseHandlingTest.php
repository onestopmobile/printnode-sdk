<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\Exceptions\ApiErrorException;
use OneStopMobile\PrintNodeSdk\Exceptions\AuthenticationException;
use OneStopMobile\PrintNodeSdk\Exceptions\AuthorizationException;
use OneStopMobile\PrintNodeSdk\Exceptions\ConflictException;
use OneStopMobile\PrintNodeSdk\Exceptions\RateLimitException;
use OneStopMobile\PrintNodeSdk\Exceptions\ResourceNotFoundException;
use OneStopMobile\PrintNodeSdk\Exceptions\ValidationException;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

dataset('mapped printnode exceptions', [
    '400 validation' => [400, ValidationException::class],
    '401 authentication' => [401, AuthenticationException::class],
    '403 authorization' => [403, AuthorizationException::class],
    '404 missing resource' => [404, ResourceNotFoundException::class],
    '409 conflict' => [409, ConflictException::class],
]);

it('maps failed responses to sdk exceptions', function (int $status, string $exceptionClass): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'invalid-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $sdk->connector()->withMockClient(new MockClient([
        'https://api.printnode.test/whoami' => MockResponse::make([
            'message' => 'Mapped failure',
        ], $status, [
            'X-Request-Id' => 'req-123',
        ]),
    ]));

    try {
        $sdk->whoAmI();
    } catch (Throwable $throwable) {
        expect($throwable)->toBeInstanceOf($exceptionClass)
            ->and($throwable->getMessage())->toBe('Mapped failure');

        return;
    }

    test()->fail('Expected a mapped PrintNode exception to be thrown.');
})->with('mapped printnode exceptions');

it('captures retry-after metadata on rate-limit responses', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'invalid-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $sdk->connector()->withMockClient(new MockClient([
        'https://api.printnode.test/whoami' => MockResponse::make([
            'message' => 'Too many requests',
        ], 429, [
            'X-Request-Id' => 'req-429',
            'Retry-After' => '120',
        ]),
    ]));

    try {
        $sdk->whoAmI();
    } catch (RateLimitException $rateLimitException) {
        expect($rateLimitException->requestId)->toBe('req-429')
            ->and($rateLimitException->retryAfter)->toBe('120')
            ->and($rateLimitException->payload)->toMatchArray([
                'message' => 'Too many requests',
            ]);

        return;
    }

    test()->fail('Expected a rate-limit exception to be thrown.');
});

it('maps unexpected failures to the generic api exception', function (): void {
    $sdk = new PrintNodeSdk(new PrintNodeConfig(
        apiKey: 'invalid-key',
        baseUrl: 'https://api.printnode.test',
    ));

    $sdk->connector()->withMockClient(new MockClient([
        'https://api.printnode.test/whoami' => MockResponse::make([
            'detail' => 'Unexpected server failure',
        ], 500, [
            'X-Request-Id' => 'req-500',
        ]),
    ]));

    $sdk->whoAmI();
})->throws(ApiErrorException::class, 'Unexpected server failure');
