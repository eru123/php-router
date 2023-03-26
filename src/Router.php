<?php

namespace eru123\router;

use stdClass;

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
        $url = trim($this->base, '/') . '/' . ltrim($url, '/');
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
            } else  if (substr_count($file, '.') > 0) {
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

        $this->static_routes[] = new Route('STATIC', $url, ...$callbacks);
        return $this;
    }

    public function run()
    {   
        header_remove('X-Powered-By');
        header_remove('Server');

        

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
