<?php

namespace Pebble\Burn;

use InvalidArgumentException;

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

    private static array $instances = [];
    private ?string $env = null;
    private array $config = [];

    // -------------------------------------------------------------------------

    public function __destruct()
    {
        $this->stop();
    }

    public static function getInstance(): static
    {
        return self::$instances[static::class] ?? (self::$instances[static::class] = new static);
    }

    public static function destroy()
    {
        if (isset(self::$instances[static::class])) {
            unset(self::$instances[static::class]);
        }
    }

    public function start() {}

    public function stop() {}

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

    public function setEnv(string $env): static
    {
        $this->env = $env;
        return $this;
    }

    public function env(): string
    {
        if ($this->env === null) {
            throw new InvalidArgumentException("Environnement is not defined");
        }

        return $this->env;
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
