<?php

namespace eru123\router;

class RouteState
{
    protected $allow_next = null;
    protected $allow_skip = null;
    protected $route = [];

    public function __construct($route = null)
    {
        if (!is_null($route) && $route instanceof Route) {
            $this->extract_info($route);
        }
    }

    final public function extract_info(Route $route)
    {
        $this->route = array_merge($this->route, $route->info());
    }

    final public function skip()
    {
        $this->allow_skip = true;
        return $this;
    }

    final public function unskip()
    {
        $this->allow_skip = false;
        return $this;
    }

    final public function next()
    {
        $this->allow_next = true;
        return $this;
    }

    final public function stop()
    {
        $this->allow_next = false;
        return $this;
    }

    final public function is_allowed_next()
    {
        return $this->allow_next;
    }

    final public function is_allowed_skip()
    {
        return $this->allow_skip;
    }

    public function __get($name)
    {   
        if (!is_array($this->route)) {
            $this->route = [];
        }

        if (isset($this->route[$name])) {
            return $this->route[$name];
        }
        
        return null;
    }

    public function _set($name, $value)
    {   
        if (in_array($name, ['allow_next', 'allow_skip', 'route'])) {
            return;
        }

        if (!is_array($this->route)) {
            $this->route = [];
        }

        if (isset($this->route[$name])) {
            $this->route[$name] = $value;
        }
    }
}