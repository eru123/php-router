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

    public function base($base)
    {
        $this->path = URL::sanitize_uri($base) . URL::sanitize_uri($this->path);

        $is_file_dir = false;

        if (in_array($this->method, ['FALLBACK', 'STATIC'])) {
            $is_file_dir = true;
            $this->is_matched = URL::dir_matched($this->path);
        } else {
            $this->is_matched = URL::matched($this->path);
        }

        if ($this->is_matched) {
            $this->params = $is_file_dir ? URL::dir_params($this->path) : URL::params($this->path);
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

        return [
            'method' => $this->method,
            'path' => $this->path,
            'params' => $this->params,
            'matched' => (bool) $this->is_matched,
            'regex' => $is_dir ? URL::create_dir_param_regex($this->path) : URL::create_param_regex($this->path),
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