<?php

namespace eru123\router;

use ReflectionClass;
use stdClass;
use Throwable;

class Router
{
    protected $routes = [];
    protected $static_routes = [];
    protected $fallback_routes = [];
    protected $state_class = RouteState::class;
    protected $error_handler = null;
    protected $response_handler = null;
    protected $base = null;
    protected $is_debug = false;
    protected $debug_data = [];
    protected $bootstrap_pipes = null;

    public function __construct()
    {
    }

    public function bootstrap(array $bootstrap)
    {
        $this->bootstrap_pipes = $bootstrap;
        return $this;
    }

    public function debug($debug = true)
    {
        $this->is_debug = $debug;
        return $this;
    }

    public function base($base)
    {
        $this->base = $base;
        array_walk($this->routes, function ($route) {
            $route->base($this->base);
        });
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
        $route = new Route($method, $url, ...$callbacks);
        if ($this->base) $route->base($this->base);
        $this->routes[] = $route;
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
        $route = new Route('FALLBACK', $url, ...$callbacks);
        if ($this->base) $route->base($this->base);
        $this->fallback_routes[] = $route;
        return $this;
    }

    public function static($url, $path = '/', ...$callbacks)
    {
        array_unshift($callbacks, function ($state) use ($path) {
            $path = str_replace('\\', '/', $path);
            $path = rtrim($path, '/') . '/';
            $file = $state->params['file'];
            $file = str_replace('/', '/', $file);
            $file = str_replace('\\', '/', $file);
            $file = ltrim($file, '/');
            $file = preg_replace('/\/?\.\//', '', $file);
            $file = $path . $file;

            $state->file = new stdClass();
            $state->file->path = $file;
            $state->file->name = basename($file);
            $state->file->mime = 'application/octet-stream';
            $state->file->allow_skip = true;

            if (function_exists('pathinfo') && defined('PATHINFO_EXTENSION') && file_exists($file)) {
                $state->file->ext = pathinfo($file, PATHINFO_EXTENSION);
            } else if (substr_count($file, '.') > 0) {
                $state->file->ext = substr($file, strrpos($file, '.') + 1);
            } else {
                $state->file->ext = $state->filename;
            }

            return $state->next();
        });

        array_push($callbacks, function ($state) {
            $mimes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'html' => 'text/html',
                'htm' => 'text/html',
                'txt' => 'text/plain',
                'csv' => 'text/csv',
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                '7z' => 'application/x-7z-compressed',
                'tar' => 'application/x-tar',
                'gz' => 'application/gzip',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mkv' => 'video/x-matroska',
                'avi' => 'video/x-msvideo',
                'flv' => 'video/x-flv',
                'wmv' => 'video/x-ms-wmv',
                'mov' => 'video/quicktime',
                'swf' => 'application/x-shockwave-flash',
                'php' => 'text/x-php',
                'asp' => 'text/asp',
                'aspx' => 'text/aspx',
                'py' => 'text/x-python',
                'rb' => 'text/x-ruby',
                'pl' => 'text/x-perl',
                'sh' => 'text/x-shellscript',
                'sql' => 'text/x-sql',
                'c' => 'text/x-csrc',
                'cpp' => 'text/x-c++src',
                'java' => 'text/x-java',
                'cs' => 'text/x-csharp',
                'vb' => 'text/x-vb',
                'ini' => 'text/x-ini',
            ];

            $ext = strtolower($state->file->ext);
            if (isset($mimes[$ext])) {
                $state->file->mime = $mimes[$ext];
            }

            return $state->next();
        });

        array_push($callbacks, function ($state) {
            if ($state->file->ext === 'php') {
                $state->file->mime = 'text/html';
                ob_start();
                include $state->file->path;
                $content = ob_get_contents();
                ob_end_clean();
                header('Content-Type: ' . $state->file->mime);
                print $content;
                exit;
            }
            return $state->next();
        });

        array_push($callbacks, function ($state) {
            if (file_exists($state->file->path)) {
                $file = fopen($state->file->path, 'r');
                header('Content-Type: ' . $state->file->mime);
                while (!feof($file)) {
                    print fread($file, 1024 * 8);
                    flush();
                }
                fclose($file);
                exit;
            }

            if ($state->file->allow_skip) {
                return $state->skip();
            }

            return $state->stop();
        });

        $route = new Route('STATIC', $url, ...$callbacks);
        if ($this->base) $route->base($this->base);
        $this->static_routes[] = $route;
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

        $state = null;

        if (is_array($this->bootstrap_pipes) && count($this->bootstrap_pipes) > 0) {
            $reflection = new ReflectionClass($this->state_class);
            $state = $reflection->newInstanceArgs([$this]);
            $state->is_debug = $this->is_debug;
            $state->debug_data_store = $this->debug_data;

            foreach ($this->bootstrap_pipes as $pipe) {
                try {
                    if (is_callable($pipe)) {
                        call_user_func_array($pipe, [$state]);
                    }
                } catch (Throwable $e) {
                    if ($state->is_debug) {
                        $state->debug_data_store = array_merge($state->debug_data_store, [
                            'error' => [
                                'message' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'trace' => $e->getTrace(),
                            ],
                        ]);
                    }

                    if (is_callable($this->error_handler)) {
                        call_user_func_array($this->error_handler, [$e, $state]);
                    }

                    throw $e;
                }
            }
        }

        foreach ($routes as $route) {
            if (!($route instanceof Route)) {
                continue;
            }

            if (!is_null($state) && $state instanceof $this->state_class) {
                $state->extract_info($route);
            }

            if ($route->matched()) {
                if ($this->is_debug) {
                    $this->debug_data['route'] = $route->info();
                    $route->error($this->error_handler)
                        ->response($this->response_handler)
                        ->state($this->state_class)
                        ->debug($this->is_debug)
                        ->debug_data($this->debug_data)
                        ->exec($state);
                    continue;
                }

                $route->error($this->error_handler)
                    ->response($this->response_handler)
                    ->state($this->state_class)
                    ->debug(false)
                    ->debug_data([])
                    ->exec($state);
            }
        }

        header('Content-Type: application/json');
        echo json_encode($this->debug_data, JSON_PRETTY_PRINT);
        exit;
    }
}