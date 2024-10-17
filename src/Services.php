<?php

namespace Pebble\Burn;

/**
 * Services
 *
 * @author mathieu
 */
abstract class Services
{
    const ENV_PROD = 'production';
    const ENV_TEST = 'testing';
    const ENV_DEV = 'developpement';

    private static array $instance = [];
    private array $config = [];

    // -------------------------------------------------------------------------

    protected function __construct()
    {
        $this->start();
    }

    public function __destruct()
    {
        $this->stop();
    }

    public static function getInstance(): static
    {
        return self::$instance[static::class] ?? (self::$instance[static::class] = new static);
    }

    public static function destroy()
    {
        if (isset(self::$instance[static::class])) {
            unset(self::$instance[static::class]);
        }
    }

    protected function start() {}

    protected function stop() {}

    // -------------------------------------------------------------------------

    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function config(string $name): mixed
    {
        return $this->config[$name] ?? null;
    }

    // -------------------------------------------------------------------------

    public function env(): string
    {
        return $this->config['env'] ?? self::ENV_PROD;
    }

    public function isProd(): bool
    {
        return $this->env() === self::ENV_PROD;
    }

    public function isTest(): bool
    {
        return $this->env() === self::ENV_TEST;
    }

    public function isDev(): bool
    {
        return $this->env() === self::ENV_DEV;
    }

    // -------------------------------------------------------------------------

    public function path(string $path = ''): string
    {
        return ($this->config['path'] ?? '') . $path;
    }

    // -------------------------------------------------------------------------
}
