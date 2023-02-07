<?php

namespace eru123\Router;

use Exception;
use Error;

class Router
{
    /**
     * Routes array for storing all routes
     * @var array
     */
    private $routes = array();
    /**
     * Base path for current router instance and it's children
     * @var string
     */
    private $base_path = '';
    /**
     * Callback for exception
     * @var callable|null
     */
    private $exception_cb = null;
    /**
     * Callback for error
     * @var callable|null
     */
    private $error_cb = null;
    /**
     * Sets the default callback for exception and error
     * @return static
     */
    public function __construct()
    {
        $default_callback = function ($e) {
            header('Content-Type: application/json');
            $http_code = is_numeric($e->getCode()) ? $e->getCode() : 500;
            http_response_code($http_code);

            $res = [
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ];

            echo json_encode($res);
            exit;
        };

        $this->exception_cb = $default_callback;
        $this->error_cb = $default_callback;
    }

    /**
     * Returns the routes array
     * @return array
     */
    public function routes()
    {
        return $this->routes;
    }
    /**
     * Base path setter and getter
     * @param string|null $base The base path of current router instance
     * @return static|string Set the base path and return the instance if $base is not null, otherwise return the base path
     */
    public function base(string $base = null)
    {
        if ($base == null) {
            return $this->base_path;
        }

        $this->base_path = rtrim($base, '/');
        return $this;
    }
    /**
     * Request handler
     * @param string $method The request method (GET, POST, PUT, DELETE, PATCH, etc.)
     * @param string $path The request path
     * @param array $pipes The pipes to be executed
     * @return static
     */
    public function request(string $method, string $path, ...$pipes)
    {
        $route = [];
        $path = $this->base_path . '/' . trim($path, '/');
        $rgx = preg_replace('/\//', "\\\/", $path);
        $rgx = preg_replace('/\{([a-zA-Z0-9]+)\}/', '(?P<$1>[a-zA-Z0-9]+)', $rgx);
        $rgx = '/^' . $rgx . '$/';
        $route['path'] = $path;
        $route['needle'] = $rgx;
        $route['method'] = strtoupper($method);
        $route['pipes'] = $pipes;
        $route['match'] = false;
        $this->routes[] = $route;
        return $this;
    }

    /**
     * Alias of request for GET method
     * @param string $path The request path
     * @param array $pipes The pipes to be executed
     * @return static
     */
    public function get(string $path, ...$pipes)
    {
        return $this->request('GET', $path, ...$pipes);
    }
    /**
     * Alias of request for POST method
     * @param string $path The request path
     * @param array $pipes The pipes to be executed
     * @return static
     */
    public function post(string $path, ...$pipes)
    {
        return $this->request('POST', $path, ...$pipes);
    }
    /**
     * Add a child router instance to current router instance
     * @param static $router The child router instance or the path to the router file
     * @return static
     */
    public function add($router)
    {
        $routes = $router->routes();
        foreach ($routes as $k => $route) {
            $route['path'] = $this->base_path . '/' . trim($route['path'], '/');
            $rgx = preg_replace('/\//', "\\\/", $route['path']);
            $rgx = preg_replace('/\{([a-zA-Z0-9]+)\}/', '(?P<$1>.*)', $rgx);
            $rgx = '/^' . $rgx . '$/';
            $route['needle'] = $rgx;
            $routes[$k] = $route;
        }

        $this->routes = array_merge($this->routes, $routes);
        return $this;
    }
    /**
     * Extract params defined in the path
     * @param array $params The params to be extracted
     * @return array The extracted params
     */
    private static function extract_params($params)
    {
        if (!is_array($params)) {
            return [];
        }

        $res = [];

        foreach ($params as $k => $v) {
            if (!is_numeric($k)) {
                $res[$k] = urldecode($v);
            }
        }

        return $res;
    }
    /**
     * Execute the router 
     * @return mixed The result of the last pipe
     */
    private function exec()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        foreach ($this->routes as $route) {
            if ($route['method'] == $method) {
                $is_match = preg_match($route['needle'], $path, $params);
                $params = self::extract_params($params);

                if ($is_match) {
                    $pipes = $route['pipes'];

                    if (count($pipes) == 0) {
                        throw new Exception("Route has no handler", 500);
                    }

                    $fpipe = array_shift($pipes);
                    $res = $fpipe($params);

                    if (!empty($pipes)) {
                        $res = is_null($res) ? [$params] : [$res];

                        foreach ($pipes as $i => $pipe) {
                            $res = call_user_func_array($pipe, $res);
                            if ($i < count($pipes) - 1) {
                                $res = is_null($res) ? [$params] : [$res];
                            }
                        }
                    }

                    return $res;
                }
            }
        }

        throw new Exception("Route not found", 404);
    }
    /**
     * Set the exception callback
     * @param callable $fn
     * @return void
     */
    public function exception($fn)
    {
        $this->exception_cb = $fn;
    }
    /**
     * Set the error callback
     * @param callable $fn
     * @return void
     */
    public function error($fn)
    {
        $this->error_cb = $fn;
    }
    /**
     * Find the route that matches the request
     * @return never
     */
    public function run()
    {
        $response = function ($res, $extra = null) {
            if (is_array($res)) {
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode($res);
                exit(0);
            } else if (is_null($res)) {
                http_response_code(204);
                exit(0);
            }

            echo $res;
            exit(0);
        };

        try {
            $res = $this->exec();
            $response($res);
        } catch (Exception $e) {
            $fn = $this->exception_cb;
            if (is_callable($fn)) {
                $res = call_user_func_array($fn, [$e]);
                $response($res);
            }
            echo $e->getMessage();
        } catch (Error $e) {
            $fn = $this->error_cb;
            $e = new Error($e->getMessage(), 500, $e);
            if (is_callable($fn)) {
                $res = call_user_func_array($fn, [$e]);
                $response($res);
            }
            echo $e->getMessage();
        }

        exit(0);
    }
}