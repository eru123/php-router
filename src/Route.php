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
    protected $uri = null;
    protected $is_matched = false;
    protected $state_class = RouteState::class;
    protected $error_handler = null;
    protected $response_handler = null;
    protected $is_debug = false;
    protected $debug_data_store = [];
    protected $router = null;

    public function __construct($method, $url, ...$handlers)
    {
        $this->method = trim(strtoupper($method));
        $this->path = $url;
        $this->uri = URL::current();
        $this->handlers = $handlers;
    }

    public function router(Router &$router = null)
    {
        if (is_null($router)) {
            return $this->router;
        }

        $this->router = $router;
        return $this;
    }

    public function map_path()
    {
        $suffix = '/' . trim($this->path, '/');
        $parent = $this->router;
        $prefix = $parent->base() ? $parent->base() : '';
        while ($parent->parent()) {
            $parent = $parent->parent();
            $base = $parent->base() ? $parent->base() : '';
            $prefix = trim($base, '/') . '/' . trim($prefix, '/');
            $prefix = trim($prefix, '/');
        }
        return '/' . trim(trim($prefix, '/') . $suffix, '/');
    }

    public function map_match()
    {
        $path = $this->map_path();
        $is_file_dir = false;

        $matched = false;

        if (in_array($this->method, ['FALLBACK', 'STATIC'])) {
            $is_file_dir = true;
            $matched = URL::dir_matched($path);
        } else {
            $matched = URL::matched($path);
        }

        if ($matched) {
            $this->params = $is_file_dir ? URL::dir_params($path) : URL::params($path);
            if (!in_array($this->method, ['ANY', 'FALLBACK', 'STATIC']) && $this->method !== trim(strtoupper($_SERVER['REQUEST_METHOD']))) {
                $matched = false;
            }
        }

        return $matched;
    }

    public function debug($debug = true)
    {
        $this->is_debug = $debug;
        return $this;
    }

    public function debug_data($data)
    {
        $this->debug_data_store = array_merge($this->debug_data_store, $data);
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
        $is_dir = in_array($this->method, ['FALLBACK', 'STATIC']);
        $path = $this->map_path();
        $matched = $this->map_match();
        return [
            'matched' => (bool) $matched,
            'method' => $this->method,
            'path' => $path,
            'params' => $this->params,
            'regex' => $is_dir ? URL::create_dir_param_regex($path) : URL::create_param_regex($path),
            'uri' => $this->uri,
        ];
    }

    public function exec($state_obj = null)
    {
        if (!$this->map_match()) {
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

        foreach ($this->handlers as $i => $handler) {
            try {
                if ($state->is_allowed_skip()) {
                    break;
                }

                if (is_null($state->is_allowed_next()) && $i > 0) {
                    throw new Error('Route handler did not call next() or stop()');
                }

                if ($state->is_allowed_next() === FALSE) {
                    break;
                }

                if (is_callable($handler)) {
                    $res = call_user_func_array($handler, [$state]);
                    continue;
                }

                throw new Error('Invalid route handler');
            } catch (Throwable $e) {
                if ($state->is_debug) {
                    $state->debug['error'] = [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace(),
                    ];
                }

                $state->stop();
                if (is_callable($this->error_handler)) {
                    $res = call_user_func_array($this->error_handler, [$e, $state]);
                    break;
                }
                throw $e;
            }
        }

        if ($state->is_allowed_skip()) {
            $state->unskip()->next();
            return null;
        }

        if (count($this->handlers) > 1 && is_null($state->is_allowed_next())) {
            throw new Error('Route handler did not call next() or stop()');
        }

        if (!$state->is_allowed_next()) {
            $state->stop();
        }

        if (is_callable($this->response_handler)) {
            $res = call_user_func_array($this->response_handler, [$res, $state]);
        }

        exit;
    }
}