<?php

namespace Pebble\Burn;

/**
 * Services
 *
 * @author mathieu
 */
class Services
{
    const ENV_PROD = 'production';
    const ENV_TEST = 'testing';
    const ENV_DEV = 'developpement';

    private static array $instance = [];
    private array $config = [];

    // -------------------------------------------------------------------------

    /**
     * Constructor
     *
     * @param array $config
     */
    protected function __construct(array $config = [])
    {
        $this->config = $config;
        self::$instance[__CLASS__] = $this;
        $this->start();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        return self::$instance[__CLASS__] ?? new static();
    }

    protected function start()
    {
    }

    protected function stop()
    {
    }

    // -------------------------------------------------------------------------

    /**
     * @return string
     */
    public function env(): string
    {
        return $this->config['env'] ?? self::ENV_PROD;
    }

    /**
     * @return boolean
     */
    public function isProd(): bool
    {
        return $this->env() === self::ENV_PROD;
    }

    /**
     * @return boolean
     */
    public function isTest(): bool
    {
        return $this->env() === self::ENV_TEST;
    }

    /**
     * @return boolean
     */
    public function isDev(): bool
    {
        return $this->env() === self::ENV_DEV;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the path of the project folder
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return ($this->config['path'] ?? '') . $path;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function config(string $name): mixed
    {
        return $this->config[$name] ?? null;
    }

    // -------------------------------------------------------------------------
}
