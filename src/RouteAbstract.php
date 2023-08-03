<?php

namespace Pebble\Burn;

abstract class RouteAbstract implements RouteInterface
{
    private string $method;
    private string $uri;
    private mixed $callback = null;
    private array $arguments = [];

    public function __construct(string $method, string $uri, mixed $callback)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->callback = $callback;
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function callback(): mixed
    {
        return $this->callback;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function execute(): mixed
    {
        throw new RouteException($this->method() . ' ' . $this->uri() . ' is not callable');
        return null;
    }
}
