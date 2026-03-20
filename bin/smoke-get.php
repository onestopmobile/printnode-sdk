<?php

declare(strict_types=1);

use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\Pagination;

require __DIR__.'/../vendor/autoload.php';

$arguments = $argv;
array_shift($arguments);

$showExtended = in_array('--extended', $arguments, true);
$showHelp = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

$apiKey = null;

foreach ($arguments as $argument) {
    if ($argument === '--extended' || $argument === '--help' || $argument === '-h') {
        continue;
    }

    $apiKey = $argument;

    break;
}

if ($showHelp || $apiKey === null || $apiKey === '') {
    fwrite(STDOUT, <<<'TEXT'
PrintNode GET smoke test

Usage:
  composer smoke:get -- <api-key>
  composer smoke:get -- <api-key> --extended

Default GET suite:
  - ping
  - whoami
  - computers
  - printers
  - printjobs (limit 5)
  - printjob states (limit 5)
  - download clients

Extended GET suite:
  - account state
  - controllable accounts
  - webhooks
  - connected scales

TEXT);

    exit($showHelp ? 0 : 1);
}

$sdk = new PrintNodeSdk(new PrintNodeConfig(apiKey: $apiKey));

$checks = [
    'ping' => static fn (): array => [
        'value' => $sdk->misc()->ping(),
    ],
    'whoami' => static fn (): array => [
        'email' => $sdk->whoAmI()->attributes['email'] ?? null,
        'accounts' => $sdk->whoAmI()->attributes['accounts'] ?? null,
    ],
    'computers' => static function () use ($sdk): array {
        $computers = $sdk->computers()->all();

        return [
            'count' => count($computers),
            'first' => $computers[0]->attributes['name'] ?? null,
        ];
    },
    'printers' => static function () use ($sdk): array {
        $printers = $sdk->printers()->all();

        return [
            'count' => count($printers),
            'first' => $printers[0]->attributes['name'] ?? null,
        ];
    },
    'printjobs(limit=5)' => static function () use ($sdk): array {
        $jobs = $sdk->printJobs()->all(new Pagination(limit: 5));

        return [
            'count' => count($jobs),
            'firstId' => $jobs[0]->attributes['id'] ?? null,
        ];
    },
    'printjob-states(limit=5)' => static function () use ($sdk): array {
        $states = $sdk->printJobs()->states(pagination: new Pagination(limit: 5));

        return [
            'count' => count($states),
        ];
    },
    'download-clients' => static function () use ($sdk): array {
        $downloads = $sdk->downloads()->all();

        return [
            'count' => count($downloads),
            'firstId' => $downloads[0]->attributes['id'] ?? null,
        ];
    },
];

if ($showExtended) {
    $checks += [
        'account-state' => static fn (): array => [
            'value' => $sdk->account()->getState(),
        ],
        'controllable-accounts' => static function () use ($sdk): array {
            $accounts = $sdk->account()->controllable();

            return [
                'count' => count($accounts),
            ];
        },
        'webhooks' => static function () use ($sdk): array {
            $webhooks = $sdk->webhooks()->all();

            return [
                'count' => count($webhooks),
                'firstId' => $webhooks[0]->attributes['id'] ?? null,
            ];
        },
        'connected-scales' => static function () use ($sdk): array {
            $scales = $sdk->scales()->listConnected();

            return [
                'count' => count($scales),
            ];
        },
    ];
}

$failures = 0;

fwrite(STDOUT, "Running PrintNode GET smoke test via SDK\n");
fwrite(STDOUT, 'Mode: '.($showExtended ? 'extended' : 'default')."\n\n");

foreach ($checks as $name => $callback) {
    try {
        $result = $callback();
        fwrite(STDOUT, '[OK] '.$name.' '.json_encode($result, JSON_UNESCAPED_SLASHES)."\n");
    } catch (Throwable $throwable) {
        $failures++;
        fwrite(STDERR, '[FAIL] '.$name.' '.$throwable::class.': '.$throwable->getMessage()."\n");
    }
}

fwrite(STDOUT, "\n");
fwrite(STDOUT, 'Checks: '.count($checks)."\n");
fwrite(STDOUT, 'Failures: '.$failures."\n");

exit($failures === 0 ? 0 : 1);
