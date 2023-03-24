<?php

namespace eru123\router;

class RouteState
{
    protected $allow_next = null;
    protected $route = [];

    public function __construct(Route $route)
    {
        $this->extract_info($route);
    }

    private function extract_info(Route $route)
    {
        $this->route = array_merge($this->route, $route->info());
    }

    final public function next()
    {
        $this->allow_next = true;
    }

    final public function stop()
    {
        $this->allow_next = false;
    }

    final public function is_allowed_next()
    {
        return $this->allow_next;
    }

    public function __get($name)
    {
        if (isset($this->route[$name])) {
            return $this->route[$name];
        }
        return null;
    }

    public function __set($name, $value)
    {
        if (isset($this->route[$name])) {
            $this->route[$name] = $value;
        }
    }
}