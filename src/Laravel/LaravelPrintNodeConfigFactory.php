<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;
use OneStopMobile\PrintNodeSdk\Printing\EnvironmentPrintDispatchPolicy;
use OneStopMobile\PrintNodeSdk\Printing\PrintManagerConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use Psr\Log\LoggerInterface;

final class LaravelPrintNodeConfigFactory
{
    public function makePrintNodeConfig(ConfigRepository $repository): PrintNodeConfig
    {
        $config = $this->rootConfig($repository);

        return new PrintNodeConfig(
            apiKey: $this->stringValue($config, 'api_key', ''),
            baseUrl: $this->stringValue($config, 'base_url', 'https://api.printnode.com'),
            userAgent: $this->stringValue($config, 'user_agent', 'onestopmobile-printnode-sdk'),
            connectTimeout: $this->floatValue($config, 'connect_timeout', 10),
            requestTimeout: $this->floatValue($config, 'request_timeout', 30),
            tries: $this->intValue($config, 'tries', 1),
            // The config is in seconds (PRINTNODE_HTTP_RETRY_INTERVAL_SECONDS); Saloon waits in milliseconds.
            retryInterval: (int) round($this->floatValue($config, 'retry_interval', 0) * 1000),
            useExponentialBackoff: $this->boolValue($config, 'use_exponential_backoff', false),
            defaultChildAccount: $this->childAccountContext($config),
        );
    }

    public function makePrintManagerConfig(ConfigRepository $repository, Container $app): PrintManagerConfig
    {
        $config = $this->rootConfig($repository);
        $printing = $this->arrayValue($config, 'printing');
        $logging = $this->arrayValue($printing, 'logging');

        return new PrintManagerConfig(
            targetResolver: $this->resolver($app, $printing),
            defaultTitle: $this->stringValue($printing, 'default_title', 'Print job'),
            defaultSource: $this->stringValue($printing, 'default_source', 'PrintNode SDK'),
            defaultOptions: $this->arrayValue($printing, 'default_options'),
            defaultIdempotencyPrefix: $this->nullableStringValue($printing, 'default_idempotency_prefix'),
            dispatchPolicy: $this->dispatchPolicy($app, $printing),
            logger: $this->logger($repository, $app, $logging),
            logSkipped: $this->boolValue($logging, 'log_skipped', true),
            logSuccess: $this->boolValue($logging, 'log_success', false),
            logFailures: $this->boolValue($logging, 'log_failures', true),
            includeContentHashInLogs: $this->boolValue($logging, 'include_content_hash', false),
            includeContentLengthInLogs: $this->boolValue($logging, 'include_content_length', true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rootConfig(ConfigRepository $repository): array
    {
        $config = $repository->get('printnode', []);

        if (! is_array($config)) {
            return [];
        }

        $normalized = [];

        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function stringValue(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function floatValue(array $config, string $key, float $default): float
    {
        $value = $config[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function intValue(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function boolValue(array $config, string $key, bool $default): bool
    {
        $value = $config[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : $default;
    }

    /**
     * @param  array<mixed>  $config
     * @return array<string, mixed>
     */
    private function arrayValue(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $itemKey => $itemValue) {
            if (is_string($itemKey)) {
                $normalized[$itemKey] = $itemValue;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function nullableStringValue(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function nullableScalarStringValue(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function childAccountContext(array $config): ?ChildAccountContext
    {
        $childAccount = $this->arrayValue($config, 'default_child_account');
        $type = $this->nullableStringValue($childAccount, 'by');
        $value = $this->nullableScalarStringValue($childAccount, 'value');

        if ($type === null || $value === null) {
            return null;
        }

        return match ($type) {
            'id' => is_numeric($value) ? ChildAccountContext::byId((int) $value) : null,
            'email' => ChildAccountContext::byEmail($value),
            'creator_ref' => ChildAccountContext::byCreatorRef($value),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $printing
     */
    private function resolver(Container $app, array $printing): ?ResolvesPrintTarget
    {
        $resolverClass = $this->nullableStringValue($printing, 'resolver');

        if ($resolverClass !== null && ! $app->bound(ResolvesPrintTarget::class)) {
            $app->bind(ResolvesPrintTarget::class, $resolverClass);
        }

        if (! $app->bound(ResolvesPrintTarget::class)) {
            return null;
        }

        return $app->make(ResolvesPrintTarget::class);
    }

    /**
     * @param  array<string, mixed>  $printing
     */
    private function dispatchPolicy(Container $app, array $printing): ?EnvironmentPrintDispatchPolicy
    {
        $policy = $this->arrayValue($printing, 'policy');

        if (! $this->boolValue($policy, 'enabled', true)) {
            return null;
        }

        $allowedEnvironments = $this->stringListValue(
            $policy['allowed_environments'] ?? ['production'],
            ['production'],
        );

        return new EnvironmentPrintDispatchPolicy(
            currentEnvironment: $this->currentEnvironment($app),
            allowedEnvironments: $allowedEnvironments === [] ? ['production'] : $allowedEnvironments,
            actionWhenDisallowed: PrintDispatchAction::tryFrom(
                $this->stringValue($policy, 'action_outside_allowed_environments', PrintDispatchAction::Skip->value)
            ) ?? PrintDispatchAction::Skip,
        );
    }

    /**
     * The channel from the config when the app defines it, otherwise the app's default logger.
     *
     * @param  array<string, mixed>  $logging
     */
    private function logger(ConfigRepository $repository, Container $app, array $logging): ?LoggerInterface
    {
        if (! $this->boolValue($logging, 'enabled', true) || ! $app->bound('log')) {
            return null;
        }

        $logManager = $app->make('log');
        $channel = $this->nullableStringValue($logging, 'channel');

        // Laravel's LogManager does not throw for a channel it does not know. It quietly switches to
        // an emergency logger that writes to laravel.log, so check that the app defines the channel.
        if (
            $channel !== null
            && is_array($repository->get('logging.channels.'.$channel))
            && is_object($logManager)
            && method_exists($logManager, 'channel')
        ) {
            $channelLogger = $logManager->channel($channel);

            if ($channelLogger instanceof LoggerInterface) {
                return $channelLogger;
            }
        }

        return $logManager instanceof LoggerInterface ? $logManager : null;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringListValue(mixed $value, array $default): array
    {
        if (is_string($value)) {
            $values = array_map(trim(...), explode(',', $value));

            $normalized = array_values(array_filter(
                $values,
                static fn (string $item): bool => $item !== '',
            ));

            return $normalized === [] ? $default : $normalized;
        }

        if (! is_array($value)) {
            return $default;
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized === [] ? $default : $normalized;
    }

    private function currentEnvironment(Container $app): string
    {
        if (method_exists($app, 'environment')) {
            $environment = $app->environment();

            if (is_string($environment) && $environment !== '') {
                return $environment;
            }
        }

        return 'production';
    }
}
