<?php

namespace Pebble\Burn;


class ControllerRoute extends RouteAbstract
{
    public function execute(): mixed
    {
        $callback = $this->callback();

        $classname = $callback[0] ?? null;
        $method = $callback[1] ?? null;

        if ($classname && $method) {
            $controller = new $classname;
            return $controller->{$method}(...$this->arguments());
        }

        return parent::execute();
    }
}
