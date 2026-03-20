<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
use Tests\Support\ArrayLogger;
use Tests\Support\FakeLogManager;

function fakeConfigRepository(array $items = []): ConfigRepository
{
    return new class($items) implements ConfigRepository
    {
        /**
         * @param  array<string, mixed>  $items
         */
        public function __construct(
            private array $items = [],
        ) {}

        public function has($key): bool
        {
            return array_key_exists((string) $key, $this->items);
        }

        public function get($key, $default = null): mixed
        {
            return $this->items[(string) $key] ?? $default;
        }

        public function all(): array
        {
            return $this->items;
        }

        public function set($key, $value = null): void
        {
            $this->items[(string) $key] = $value;
        }

        public function prepend($key, $value): void
        {
            $existing = $this->items[(string) $key] ?? [];
            $existing = is_array($existing) ? $existing : [$existing];
            array_unshift($existing, $value);
            $this->items[(string) $key] = $existing;
        }

        public function push($key, $value): void
        {
            $existing = $this->items[(string) $key] ?? [];
            $existing = is_array($existing) ? $existing : [$existing];
            $existing[] = $value;
            $this->items[(string) $key] = $existing;
        }
    };
}

function fakeLaravelApp(array $config = [], string $environment = 'production', ?LoggerInterface $logger = null): Container
{
    return new class(fakeConfigRepository($config), $environment, $logger) extends Container
    {
        public function __construct(ConfigRepository $config, private readonly string $environment, ?LoggerInterface $logger)
        {
            $this->instance('config', $config);
            $this->instance('log', new FakeLogManager($logger ?? new ArrayLogger));
        }

        public function configPath(string $path = ''): string
        {
            $base = '/tmp/laravel-config';

            return $path === '' ? $base : $base.'/'.ltrim($path, '/');
        }

        public function environment(...$environments): string|bool
        {
            if ($environments === []) {
                return $this->environment;
            }

            return in_array($this->environment, $environments, true);
        }
    };
}
