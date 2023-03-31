<?php

namespace eru123\router;

use Exception;
use ReflectionClass;
use stdClass;
use Throwable;

class Router
{
    protected $is_child = false;
    protected $childs = [];
    protected $parent = null;
    protected $is_bootsrapped = false;
    protected $routes = [];
    protected $static_routes = [];
    protected $fallback_routes = [];
    protected $state_class = RouteState::class;
    protected $error_handler = [Builtin::class, 'error'];
    protected $response_handler = [Builtin::class, 'response'];
    protected $base = null;
    protected $is_debug = false;
    protected $debug_data = [];
    protected $is_bootstrap_run = false;
    protected $bootstrap_pipes = [
        [Builtin::class, 'cors'],
        [Builtin::class, 'parse_body'],
        [Builtin::class, 'parse_query'],
        [Builtin::class, 'parse_xml_body'],
        [Builtin::class, 'parse_json_body'],
        [Builtin::class, 'remove_header_ads'],
    ];

    public function __construct()
    {
    }

    public function bootstrap(array $bootstrap)
    {
        $this->bootstrap_pipes = $bootstrap;
        $this->is_bootsrapped = true;
        return $this;
    }

    public function run_bootstrap(&$state = null)
    {
        if ($this->is_bootstrap_run) {
            return $this;
        }

        if (!$this->is_bootsrapped && !$this->parent()) {
            return $this;
        }

        if (!(is_array($this->bootstrap_pipes) && count($this->bootstrap_pipes) > 0)) {
            return $this;
        }

        $this->is_bootstrap_run = true;
        if ($this->parent()) {
            $this->parent()->run_bootstrap($state);
        }

        if (is_null($state)) {
            $reflection = new ReflectionClass($this->state_class);
            $state = $reflection->newInstanceArgs([$this]);
            $state->is_debug = $this->is_debug;
            $state->debug_data_store = $this->debug_data;
        }

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

    public function set_as_child()
    {
        $this->is_child = true;
        return $this;
    }

    public function child(Router $router)
    {
        $router->set_as_child();
        $router->parent($this);

        $this->childs[] = $router;
        return $this;
    }

    public function parent(&$router = null)
    {
        if (is_null($router)) {
            return $this->parent;
        }

        $this->parent = $router;
        return null;
    }

    public function debug($debug = true)
    {
        $this->is_debug = $debug;
        return $this;
    }

    public function base($base = null)
    {
        if (is_null($base)) {
            return $this->base;
        }

        $this->base = $base;
        return $this;
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
        // if ($this->base)
        //     $route->base($this->base);
        $route->router($this);
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

    public function any($url, ...$callbacks)
    {
        return $this->request('ANY', $url, ...$callbacks);
    }

    public function fallback($url, ...$callbacks)
    {
        $route = new Route('FALLBACK', $url, ...$callbacks);
        // if ($this->base)
        //     $route->base($this->base);
        $route->router($this);
        $this->fallback_routes[] = $route;
        return $this;
    }

    public function static($url, $path = '/', ...$callbacks)
    {
        array_unshift($callbacks, function ($state) use ($path) {
            $path = realpath($path);

            if (empty($path)) {
                return $state->skip();
            }

            $path = str_replace('\\', '/', $path);
            $path = rtrim($path, '/') . '/';
            $file = @$state->params['file'];

            if (empty($file)) {
                return $state->skip();
            }

            $file = str_replace('/', '/', $file);
            $file = str_replace('\\', '/', $file);
            $file = ltrim($file, '/');
            $file = $path . $file;
            $file = realpath($file);

            if (strpos($file, realpath($path)) !== 0 || !file_exists($file)) {
                return $state->skip();
            }

            $state->file = new stdClass();
            $state->file->basedir = $path;
            $state->file->path = $file;
            $state->file->name = basename($file);
            $state->file->mime = 'application/octet-stream';
            $state->file->allow_skip = true;
            $state->file->ext = null;

            if (function_exists('pathinfo') && defined('PATHINFO_EXTENSION') && file_exists($file)) {
                $state->file->ext = pathinfo($file, PATHINFO_EXTENSION);
            } else if (substr_count($file, '.') > 0) {
                $state->file->ext = substr($file, strrpos($file, '.') + 1);
            }

            $state->file->ext = !is_string($state->file->ext) ? $state->filename : $state->file->ext;
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

            $ext = strtolower($state->file->ext ?? '');
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
        // if ($this->base)
        //     $route->base($this->base);
        $route->router($this);
        $this->static_routes[] = $route;
        return $this;
    }

    public function allroutes()
    {
        return array_merge($this->static_routes, $this->routes, $this->fallback_routes);
    }

    function has_childs()
    {
        return count($this->childs) > 0;
    }

    function routes_map()
    {
        $routes = $this->allroutes();
        if ($this->has_childs()) {
            foreach ($this->childs as $child) {
                $routes = array_merge($child->routes_map(), $routes);
            }
        }

        return $routes;
    }

    function routes_map_info()
    {
        $routes = $this->routes_map();
        $info = array_map(
            function ($route) {
                return $route->info();
            },
            $routes
        );

        return $info;
    }

    public function run(?string $base = null)
    {
        $this->base($base);

        if ($this->is_debug) {
            $this->debug_data['routes'] = $this->routes_map_info();
        }

        $state = null;

        foreach ($this->routes_map() as $route) {
            if (!($route instanceof Route)) {
                continue;
            }

            if ($this->is_debug && isset($this->debug_data['route'])) {
                if (!isset($this->debug_data['skipped_routes'])) {
                    $this->debug_data['skipped_routes'] = [];
                }
                $this->debug_data['skipped_routes'][] = $this->debug_data['route'];
                unset($this->debug_data['route']);
            }

            if ($route->map_match()) {
                $route->router()->run_bootstrap($state);
                if (is_object($state) && method_exists($state, 'extract_info')) {
                    $state->extract_info($route);
                }

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

        if (!$this->is_child && $this->is_debug) {
            header('Content-Type: application/json');
            echo json_encode($this->debug_data, JSON_PRETTY_PRINT);
            exit;
        }

        if (!$this->is_child) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
    }
}
