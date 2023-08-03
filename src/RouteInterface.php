<?php

namespace Pebble\Burn;

interface RouteInterface
{
    public function setArguments(array $arguments): static;
    public function method(): string;
    public function uri(): string;
    public function callback(): mixed;
    public function arguments(): array;
    public function execute(): mixed;
}
