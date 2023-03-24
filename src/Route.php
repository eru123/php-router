<?php

namespace eru123\router;

use Error;
use ReflectionClass;
use Throwable;

class Route
{
    protected $method = 'POST';
    protected $handlers = [];
    protected $params = [];
    protected $path = null;
    protected $is_matched = false;
    protected $state_class = RouteState::class;
    protected $error_handler = null;
    protected $response_handler = null;
    protected $is_debug = false;
    protected $debug_data_store = [];

    public function __construct($method, $url, ...$handlers)
    {
        $this->method = trim(strtoupper($method));
        $this->path = $url;
        $this->handlers = $handlers;
        $is_file_dir = false;

        if (in_array($this->method, ['FALLBACK', 'STATIC'])) {
            $is_file_dir = true;
            $this->is_matched = URL::dir_matched($url);
        } else {
            $this->is_matched = URL::matched($url);
        }

        if ($this->is_matched) {
            $this->params = $is_file_dir ? URL::dir_params($url) : URL::params($url);
            if (!in_array($this->method, ['ANY', 'FALLBACK', 'STATIC']) && $this->method !== trim(strtoupper($_SERVER['REQUEST_METHOD']))) {
                $this->is_matched = false;
            }
        }
    }

    public function debug($debug = true)
    {
        $this->is_debug = $debug;
        return $this;
    }

    public function debug_data($data)
    {
        $this->debug_data_store[] = $data;
        return $this;
    }

    public function error($handler)
    {
        $this->error_handler = $handler;
        return $this;
    }

    public function response($handler)
    {
        $this->response_handler = $handler;
        return $this;
    }

    public function state($state_class = null)
    {
        if ($state_class !== null) {
            $this->state_class = $state_class;
            return $this;
        }

        return $this->state_class;
    }

    public function info()
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'params' => $this->params,
            'matched' => (bool) $this->is_matched,
        ];
    }

    public function matched()
    {
        return (bool) $this->is_matched;
    }

    public function exec($state_obj = null)
    {
        if (!$this->is_matched) {
            return;
        }

        if (!is_null($state_obj)) {
            $state = $state_obj;
        } else {
            $reflection = new ReflectionClass($this->state_class);
            $state = $reflection->newInstanceArgs([$this]);
        }

        if ($this->is_debug) {
            $state->is_debug = true;
            $state->debug = $this->debug_data_store;
        }

        $res = null;

        foreach ($this->handlers as $handler) {
            try {
                if ($state->is_allowed_skip()) {
                    break;
                }

                if (is_null($state->is_allowed_next())) {
                    throw new Error('Route handler did not call next() or stop()');
                }

                if (!$state->is_allowed_next()) {
                    break;
                }

                if (is_callable($handler)) {
                    $res = call_user_func_array($handler, [$state]);
                    continue;
                }

                throw new Error('Invalid route handler');
            } catch (Throwable $e) {
                $state->stop();
                if (is_callable($this->error_handler)) {
                    $res = call_user_func_array($this->error_handler, [$e, $state]);
                    break;
                }
                throw $e;
            }
        }

        if (count($this->handlers) > 1 && is_null($state->is_allowed_next())) {
            throw new Error('Route handler did not call next() or stop()');
        }

        if ($state->is_allowed_skip()) {
            $state->unskip()->next();
            return null;
        }

        if (!$state->is_allowed_next()) {
            $state->stop();
        }

        if (is_callable($this->response_handler)) {
            $res = call_user_func_array($this->response_handler, [$res, $state]);
        }

        return $res;
    }
}