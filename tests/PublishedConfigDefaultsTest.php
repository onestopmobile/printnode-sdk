<?php

declare(strict_types=1);

function loadPublishedPrintNodeConfig(array $overrides = []): array
{
    $keys = [
        'PRINTNODE_API_KEY',
        'PRINTNODE_API_BASE_URL',
        'PRINTNODE_HTTP_USER_AGENT',
        'PRINTNODE_HTTP_CONNECT_TIMEOUT_SECONDS',
        'PRINTNODE_HTTP_REQUEST_TIMEOUT_SECONDS',
        'PRINTNODE_HTTP_RETRY_ATTEMPTS',
        'PRINTNODE_HTTP_RETRY_INTERVAL_SECONDS',
        'PRINTNODE_HTTP_USE_EXPONENTIAL_BACKOFF',
        'PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_FIELD',
        'PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_VALUE',
        'PRINTNODE_PRINT_DEFAULT_TITLE',
        'PRINTNODE_PRINT_DEFAULT_SOURCE',
        'PRINTNODE_PRINT_IDEMPOTENCY_KEY_PREFIX',
        'PRINTNODE_PRINT_GUARD_ENABLED',
        'PRINTNODE_PRINT_ALLOWED_ENVIRONMENTS',
        'PRINTNODE_PRINT_ACTION_OUTSIDE_ALLOWED_ENVIRONMENTS',
        'PRINTNODE_PRINT_LOGGING_ENABLED',
        'PRINTNODE_PRINT_LOG_CHANNEL',
        'PRINTNODE_PRINT_LOG_SKIPPED_JOBS',
        'PRINTNODE_PRINT_LOG_SUCCESSFUL_JOBS',
        'PRINTNODE_PRINT_LOG_FAILED_JOBS',
        'PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_HASH',
        'PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_LENGTH',
    ];

    $originals = [];

    foreach ($keys as $key) {
        $value = getenv($key);
        $originals[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'getenv' => $value === false ? null : $value,
            'exists_env' => array_key_exists($key, $_ENV),
            'exists_server' => array_key_exists($key, $_SERVER),
            'exists_getenv' => $value !== false,
        ];

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    foreach ($overrides as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require __DIR__.'/../config/printnode.php';

        return $config;
    } finally {
        foreach ($originals as $key => $original) {
            if ($original['exists_env']) {
                $_ENV[$key] = $original['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($original['exists_server']) {
                $_SERVER[$key] = $original['server'];
            } else {
                unset($_SERVER[$key]);
            }

            if ($original['exists_getenv']) {
                putenv($key.'='.$original['getenv']);
            } else {
                putenv($key);
            }
        }
    }
}

it('uses explicit published config defaults that skip outside production and log by default', function (): void {
    $config = loadPublishedPrintNodeConfig();

    expect($config['base_url'])->toBe('https://api.printnode.com')
        ->and($config['user_agent'])->toBe('onestopmobile-printnode-sdk')
        ->and($config['default_child_account'])->toBe([
            'by' => null,
            'value' => null,
        ])
        ->and($config['printing']['default_title'])->toBe('Backoffice print job')
        ->and($config['printing']['default_source'])->toBe('One Stop Mobile - Backoffice')
        ->and($config['printing']['policy'])->toBe([
            'enabled' => true,
            'allowed_environments' => 'production',
            'action_outside_allowed_environments' => 'skip',
        ])
        ->and($config['printing']['logging'])->toBe([
            'enabled' => true,
            'channel' => 'print-node',
            'log_skipped' => true,
            'log_success' => false,
            'log_failures' => true,
            'include_content_hash' => false,
            'include_content_length' => true,
        ]);
});

it('uses the explicit env names directly', function (): void {
    $config = loadPublishedPrintNodeConfig([
        'PRINTNODE_API_BASE_URL' => 'https://api.printnode.new',
        'PRINTNODE_HTTP_USER_AGENT' => 'new-agent',
        'PRINTNODE_HTTP_RETRY_ATTEMPTS' => '3',
        'PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_FIELD' => 'email',
        'PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_VALUE' => 'warehouse@example.com',
        'PRINTNODE_PRINT_DEFAULT_TITLE' => 'Recommended title',
        'PRINTNODE_PRINT_ALLOWED_ENVIRONMENTS' => 'production, staging',
        'PRINTNODE_PRINT_LOG_CHANNEL' => 'print-node',
        'PRINTNODE_PRINT_LOG_SUCCESSFUL_JOBS' => 'true',
    ]);

    expect($config['base_url'])->toBe('https://api.printnode.new')
        ->and($config['user_agent'])->toBe('new-agent')
        ->and($config['tries'])->toBe(3)
        ->and($config['default_child_account'])->toBe([
            'by' => 'email',
            'value' => 'warehouse@example.com',
        ])
        ->and($config['printing']['default_title'])->toBe('Recommended title')
        ->and($config['printing']['policy']['allowed_environments'])->toBe('production, staging')
        ->and($config['printing']['logging']['channel'])->toBe('print-node')
        ->and($config['printing']['logging']['log_success'])->toBeTrue();
});
