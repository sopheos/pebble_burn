<?php

class ControllerWithDependency
{
    public function __construct(public string $dependency)
    {
    }

    public function index()
    {
        return $this->dependency;
    }
}
