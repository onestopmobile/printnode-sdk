<?php

declare(strict_types=1);

return [
    'api_key' => env('PRINTNODE_API_KEY', ''),
    'base_url' => env('PRINTNODE_API_BASE_URL', 'https://api.printnode.com'),
    'user_agent' => env('PRINTNODE_HTTP_USER_AGENT', 'onestopmobile-printnode-sdk'),
    'connect_timeout' => (float) env('PRINTNODE_HTTP_CONNECT_TIMEOUT_SECONDS', 10),
    'request_timeout' => (float) env('PRINTNODE_HTTP_REQUEST_TIMEOUT_SECONDS', 30),
    'tries' => (int) env('PRINTNODE_HTTP_RETRY_ATTEMPTS', 1),
    'retry_interval' => (int) env('PRINTNODE_HTTP_RETRY_INTERVAL_SECONDS', 0),
    'use_exponential_backoff' => env('PRINTNODE_HTTP_USE_EXPONENTIAL_BACKOFF', false),
    'default_child_account' => [
        'by' => env('PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_FIELD'),
        'value' => env('PRINTNODE_DEFAULT_CHILD_ACCOUNT_LOOKUP_VALUE'),
    ],
    'printing' => [
        'default_title' => env('PRINTNODE_PRINT_DEFAULT_TITLE', 'Backoffice print job'),
        'default_source' => env('PRINTNODE_PRINT_DEFAULT_SOURCE', 'One Stop Mobile - Backoffice'),
        'default_options' => [],
        'default_idempotency_prefix' => env('PRINTNODE_PRINT_IDEMPOTENCY_KEY_PREFIX'),
        'resolver' => null,
        'policy' => [
            'enabled' => env('PRINTNODE_PRINT_GUARD_ENABLED', true),
            'allowed_environments' => env('PRINTNODE_PRINT_ALLOWED_ENVIRONMENTS', 'production'),
            'action_outside_allowed_environments' => env('PRINTNODE_PRINT_ACTION_OUTSIDE_ALLOWED_ENVIRONMENTS', 'skip'),
        ],
        'logging' => [
            'enabled' => env('PRINTNODE_PRINT_LOGGING_ENABLED', true),
            'channel' => env('PRINTNODE_PRINT_LOG_CHANNEL', 'print-node'),
            'log_skipped' => env('PRINTNODE_PRINT_LOG_SKIPPED_JOBS', true),
            'log_success' => env('PRINTNODE_PRINT_LOG_SUCCESSFUL_JOBS', false),
            'log_failures' => env('PRINTNODE_PRINT_LOG_FAILED_JOBS', true),
            'include_content_hash' => env('PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_HASH', false),
            'include_content_length' => env('PRINTNODE_PRINT_LOG_INCLUDE_CONTENT_LENGTH', true),
        ],
    ],
];
