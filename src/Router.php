<?php

namespace eru123\router;

class Router
{
    protected $routes = [];
    protected $static_routes = [];
    protected $fallback_routes = [];
    protected $state_class = RouteState::class;
    protected $error_handler = null;
    protected $response_handler = null;
    protected $base = '';
    protected $is_debug = false;
    protected $debug_data = [];

    public function __construct()
    {
    }

    public function debug($debug = true)
    {
        $this->is_debug = $debug;
        return $this;
    }

    public function base($base)
    {
        $this->base = $base;
    }

    public function state($state_class = null)
    {
        if (!empty($state_class)) {
            $this->state_class = $state_class;
        }
        return $this->state_class;
    }

    public function error($error_handler = null)
    {
        if (!empty($error_handler)) {
            $this->error_handler = $error_handler;
        }
        return $this->error_handler;
    }

    public function response($response_handler = null)
    {
        if (!empty($response_handler)) {
            $this->response_handler = $response_handler;
        }
        return $this->response_handler;
    }

    public function request($method, $url, ...$callbacks)
    {
        $url = trim($this->base, '/') . '/' . ltrim($url, '/');
        $this->routes[] = new Route($method, $url, ...$callbacks);
        return $this;
    }

    public function get($url, ...$callbacks)
    {
        return $this->request('GET', $url, ...$callbacks);
    }

    public function post($url, ...$callbacks)
    {
        return $this->request('POST', $url, ...$callbacks);
    }

    public function put($url, ...$callbacks)
    {
        return $this->request('PUT', $url, ...$callbacks);
    }

    public function delete($url, ...$callbacks)
    {
        return $this->request('DELETE', $url, ...$callbacks);
    }

    public function patch($url, ...$callbacks)
    {
        return $this->request('PATCH', $url, ...$callbacks);
    }

    public function any($url, ...$callbacks)
    {
        return $this->request('ANY', $url, ...$callbacks);
    }

    public function fallback($url, ...$callbacks)
    {
        $url = trim($this->base, '/') . '/' . ltrim($url, '/');
        $this->fallback_routes[] = new Route('FALLBACK', $url, ...$callbacks);
        return $this;
    }

    public function static($url, $path = '/', ...$callbacks)
    {
        $url = $this->base . $url;
        array_unshift($callbacks, function (&$state) use ($path){
            $ds = DIRECTORY_SEPARATOR;
            $path = str_replace('/', $ds, $path);
            $path = str_replace('\\', $ds, $path);
            $path = rtrim($path, $ds) . $ds;
            $file = $state->params['file'];
            $file = str_replace('/', $ds, $file);
            $file = str_replace('\\', $ds, $file);
            $file = ltrim($file, $ds);
            $file = preg_replace("/$ds?\.$ds/", '', $file);
            $file = $path . $file;
            $state->filepath = $file;
            $state->next();
        });
        array_push($callbacks, function (&$state) {
            $file = $state->filepath;
            if (file_exists($file)) {
                $file = fopen($file, 'r');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                while (!feof($file)) {
                    print fread($file, 1024 * 8);
                    flush();
                }
                fclose($file);
                exit;
            }
        });
        $this->static_routes[] = new Route('STATIC', $url, ...$callbacks);
        return $this;
    }

    public function run()
    {
        $routes = array_merge($this->static_routes, $this->routes, $this->fallback_routes);

        if ($this->is_debug) {
            $this->debug_data['routes'] = array_map(function ($route) {
                return $route->info();
            }, $routes);
        }

        foreach ($routes as $route) {
            if (!($route instanceof Route)) {
                continue;
            }

            if ($route->matched()) {
                if ($this->is_debug) {
                    $this->debug_data['route'] = $route->info();
                    $route->error($this->error_handler)
                        ->response($this->response_handler)
                        ->state($this->state_class)
                        ->debug($this->is_debug)
                        ->debug_data($this->debug_data)
                        ->exec();
                    continue;
                }

                $route->error($this->error_handler)
                    ->response($this->response_handler)
                    ->state($this->state_class)
                    ->debug(false)
                    ->debug_data([])
                    ->exec();
            }
        }
        header('Content-Type: application/json');
        echo json_encode($this->debug_data, JSON_PRETTY_PRINT);

        exit;
    }
}